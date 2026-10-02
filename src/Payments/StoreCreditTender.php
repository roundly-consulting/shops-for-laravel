<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Payments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RoundlyConsulting\Credits\Interfaces\Creditable;
use RoundlyConsulting\Money\Exceptions\CurrencyMismatch;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Payments\Exceptions\StoreCreditAlreadyAppliedException;
use RoundlyConsulting\Shops\Payments\Exceptions\StoreCreditBucketNotDenominated;
use RoundlyConsulting\Shops\Payments\Exceptions\StoreCreditCurrencyMismatch;

/**
 * Pays for an order with the buyer's store credit — a credits-for-laravel bucket
 * **denominated** in the order's currency (`credits.currencies`) — in full or in part.
 * Debits the lesser of the balance, the order total, and any requested cap; records the
 * applied amount on the order; and returns the remainder still owed to the gateway.
 * Wrapped in a transaction; guards against double application — also from a stale copy of
 * the order, by re-reading `store_credit_applied` under the order's row lock.
 *
 * {@see self::restore()} hands applied credit back — canceling an order runs it, so credit an
 * order never got to keep is never lost.
 */
final class StoreCreditTender
{
    /**
     * @return Money The remaining amount to charge through the gateway.
     *
     * @throws StoreCreditBucketNotDenominated when the bucket has no currency.
     * @throws StoreCreditCurrencyMismatch when the bucket's currency is not the order's.
     * @throws CurrencyMismatch when `$amount` is in another currency than the order.
     */
    public function apply(Order $order, Model&Creditable $customer, ?Money $amount = null): Money
    {
        if ($order->store_credit_applied !== null) {
            throw StoreCreditAlreadyAppliedException::forOrder($order->number);
        }

        $total = $order->price->getFinalPrice();
        $bucket = $this->bucket();

        $currency = $customer->creditsCurrency($bucket)
            ?? throw StoreCreditBucketNotDenominated::forBucket($bucket);

        if (! $currency->equals($total->currency())) {
            throw StoreCreditCurrencyMismatch::between($bucket, $currency, $total->currency());
        }

        $balance = $customer->creditsBalanceMoney($bucket);

        $applied = Money::max([
            Money::zero($total->currency()),
            Money::min([$total, $balance, $amount ?? $total]),
        ]);

        if ($applied->isZero()) {
            return $total;
        }

        return DB::transaction(function () use ($order, $customer, $bucket, $applied, $total): Money {
            // Re-check under the order's row lock: the in-memory copy may be stale (a second
            // request that loaded the order before the first one paid with credit).
            $this->assertNotYetApplied($order);

            $customer->modifyCreditsMoney(
                $applied->negate(),
                description: "Store credit applied to order {$order->number}",
                bucket: $bucket,
            );

            $order->store_credit_applied = $applied;
            $order->save();

            return $total->subtract($applied);
        });
    }

    /**
     * Return the store credit applied to an order to the buyer's bucket and clear
     * `store_credit_applied`. The applied amount is read under the order's row lock, so a stale
     * copy of the order returns exactly what is stored — and a second call returns nothing.
     * Canceling an order runs this. A buyer that is no longer creditable keeps the amount on the
     * order (logged) for a manual fix.
     *
     * @return Money|null The amount returned, or null when there was none to return.
     */
    public function restore(Order $order): ?Money
    {
        return DB::transaction(function () use ($order): ?Money {
            $applied = $this->lockedApplied($order);

            if (! $applied instanceof Money || ! $applied->isPositive()) {
                return null;
            }

            $customer = $order->customer;

            if (! $customer instanceof Model || ! $customer instanceof Creditable) {
                Log::warning('Store credit not returned: the order\'s buyer is not creditable.', [
                    'order' => $order->number,
                    'store_credit_applied' => (string) $applied,
                ]);

                return null;
            }

            $customer->modifyCreditsMoney(
                $applied,
                description: "Store credit returned from order {$order->number}",
                bucket: $this->bucket(),
            );

            // A query update, not save(): a stale copy may already hold null in memory, which
            // save() would see as clean and skip.
            $order->newQueryWithoutScopes()->whereKey($order->getKey())->update(['store_credit_applied' => null]);
            $order->setAttribute('store_credit_applied', null);
            $order->syncOriginalAttribute('store_credit_applied');

            return $applied;
        });
    }

    private function lockedApplied(Order $order): ?Money
    {
        if (! $order->exists) {
            return $order->store_credit_applied;
        }

        $locked = $order->newQueryWithoutScopes()
            ->whereKey($order->getKey())
            ->lockForUpdate()
            ->first([$order->getKeyName(), 'currency', 'store_credit_applied']);

        return $locked?->store_credit_applied;
    }

    /**
     * @throws StoreCreditAlreadyAppliedException
     */
    private function assertNotYetApplied(Order $order): void
    {
        $locked = $order->newQueryWithoutScopes()
            ->whereKey($order->getKey())
            ->lockForUpdate()
            ->first([$order->getKeyName(), 'currency', 'store_credit_applied']);

        if ($locked?->store_credit_applied !== null) {
            throw StoreCreditAlreadyAppliedException::forOrder($order->number);
        }
    }

    private function bucket(): string
    {
        return (string) config('shops.payment.store_credit_bucket', 'store_credit');
    }
}
