<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Contracts;

use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Payments\PaymentResult;

/**
 * Contract a host application's payment gateway implements. The package ships
 * no vendor adapter; bind your own implementation via `shops.payment.gateway`.
 */
interface PaymentGateway
{
    /**
     * Charge `$order->gatewayAmount()` — the final price minus any store credit already
     * applied. ChargeOrderAction never calls this for a zero balance.
     */
    public function charge(Order $order): PaymentResult;

    /**
     * Refund `$amount` (at most `$order->gatewayAmount()`). The package never calls this
     * itself: refunds are host-driven — refund through the gateway, then `$order->refund()`.
     */
    public function refund(Order $order, Money $amount): PaymentResult;
}
