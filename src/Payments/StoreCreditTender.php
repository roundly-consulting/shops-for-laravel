<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Payments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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
 * Wrapped in a transaction; guards against double application.
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

    private function bucket(): string
    {
        return (string) config('shops.payment.store_credit_bucket', 'store_credit');
    }
}
