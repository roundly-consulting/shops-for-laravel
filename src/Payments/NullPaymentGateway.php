<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Payments;

use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Contracts\PaymentGateway;
use RoundlyConsulting\Shops\Orders\Order;

/**
 * Default gateway that always succeeds without taking real payment, so the
 * package works zero-config. It reports the order's gateway amount — the balance
 * left after store credit — as charged. Replace it with a real gateway in production.
 */
final class NullPaymentGateway implements PaymentGateway
{
    public function charge(Order $order): PaymentResult
    {
        return PaymentResult::success($order->gatewayAmount());
    }

    public function refund(Order $order, Money $amount): PaymentResult
    {
        return PaymentResult::success($amount);
    }
}
