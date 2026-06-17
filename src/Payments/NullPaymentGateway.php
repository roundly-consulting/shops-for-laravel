<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Payments;

use RoundlyConsulting\Shops\Contracts\PaymentGateway;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Support\Money\Money;

/**
 * Default gateway that always succeeds without taking real payment, so the
 * package works zero-config. Replace it with a real gateway in production.
 */
final class NullPaymentGateway implements PaymentGateway
{
    public function charge(Order $order): PaymentResult
    {
        return PaymentResult::success($order->price->getFinalPrice());
    }

    public function refund(Order $order, Money $amount): PaymentResult
    {
        return PaymentResult::success($amount);
    }
}
