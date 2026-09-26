<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Payments\Listeners;

use Illuminate\Support\Facades\Log;
use RoundlyConsulting\Credits\Interfaces\Creditable;
use RoundlyConsulting\Shops\Orders\Events\OrderRefunded;

/**
 * When store-credit refunds are enabled (`shops.payment.refund_to_store_credit`),
 * grants the refunded order's total back to the buyer's store-credit bucket
 * instead of (or in addition to) a gateway refund. No-op when the order has no
 * creditable customer. A bucket that is not denominated in the order's currency is
 * skipped with a warning rather than thrown: the refund has already happened, and a
 * listener must not fail it.
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

        $final = $event->order->price->getFinalPrice();

        if (! $final->isPositive()) {
            return;
        }

        $bucket = (string) config('shops.payment.store_credit_bucket', 'store_credit');
        $currency = $customer->creditsCurrency($bucket);

        if ($currency === null || ! $currency->equals($final->currency())) {
            Log::warning('Store-credit refund skipped: the bucket is not denominated in the order currency.', [
                'order' => $event->order->number,
                'bucket' => $bucket,
                'bucket_currency' => $currency?->code,
                'order_currency' => $final->currency()->code,
            ]);

            return;
        }

        $customer->modifyCreditsMoney(
            $final,
            description: "Store credit refund for order {$event->order->number}",
            bucket: $bucket,
        );
    }
}
