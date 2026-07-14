<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Payments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Credits\Interfaces\Creditable;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Payments\Exceptions\StoreCreditAlreadyAppliedException;
use RoundlyConsulting\Shops\Support\Money\Money;

/**
 * Pays for an order with the buyer's store credit (a named credits bucket from
 * roundly-consulting/credits-for-laravel), in full or in part. Debits the
 * lesser of the balance, the order total, and any requested cap; records the
 * applied amount on the order; and returns the remainder still owed to the
 * gateway. Wrapped in a transaction; guards against double application.
 */
final class StoreCreditTender
{
    /**
     * @return Money The remaining amount to charge through the gateway.
     */
    public function apply(Order $order, Model&Creditable $customer, ?Money $amount = null): Money
    {
        if ($order->store_credit_applied !== null) {
            throw StoreCreditAlreadyAppliedException::forOrder($order->number);
        }

        $total = $order->price->getFinalPrice();
        $currency = $total->getCurrency()->getCode();
        $bucket = $this->bucket();

        $balance = $customer->creditsBalance(bucket: $bucket);
        $cap = $amount?->getMinorAmount() ?? $total->getMinorAmount();

        $applied = max(0, min($total->getMinorAmount(), $balance, $cap));

        if ($applied === 0) {
            return $total;
        }

        return DB::transaction(function () use ($order, $customer, $bucket, $applied, $total, $currency): Money {
            $customer->modifyCredits(
                -$applied,
                description: "Store credit applied to order {$order->number}",
                bucket: $bucket,
            );

            $order->store_credit_applied = $applied;
            $order->save();

            return Money::of($total->getMinorAmount() - $applied, $currency);
        });
    }

    private function bucket(): string
    {
        return (string) config('shops.payment.store_credit_bucket', 'store_credit');
    }
}
