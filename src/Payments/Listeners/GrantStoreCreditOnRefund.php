<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Payments\Listeners;

use RoundlyConsulting\Credits\Interfaces\Creditable;
use RoundlyConsulting\Shops\Orders\Events\OrderRefunded;

/**
 * When store-credit refunds are enabled (`shops.payment.refund_to_store_credit`),
 * granting the refunded order's total back to the buyer's store-credit bucket
 * instead of (or in addition to) a gateway refund. No-op when the order has no
 * creditable customer.
 */
final class GrantStoreCreditOnRefund
{
    public function handle(OrderRefunded $event): void
    {
        if (! (bool) config('shops.payment.refund_to_store_credit', false)) {
            return;
        }

        $customer = $event->order->customer;

        if (! $customer instanceof Creditable) {
            return;
        }

        $amount = $event->order->price->getFinalPrice()->getMinorAmount();

        if ($amount <= 0) {
            return;
        }

        $customer->modifyCredits(
            $amount,
            description: "Store credit refund for order {$event->order->number}",
            bucket: (string) config('shops.payment.store_credit_bucket', 'store_credit'),
        );
    }
}
