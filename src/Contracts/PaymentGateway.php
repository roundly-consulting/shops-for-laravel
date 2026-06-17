<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Contracts;

use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Payments\PaymentResult;
use RoundlyConsulting\Shops\Support\Money\Money;

/**
 * Contract a host application's payment gateway implements. The package ships
 * no vendor adapter; bind your own implementation via `shops.payment.gateway`.
 */
interface PaymentGateway
{
    public function charge(Order $order): PaymentResult;

    public function refund(Order $order, Money $amount): PaymentResult;
}
