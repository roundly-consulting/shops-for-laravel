<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Contracts;

use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Address;
use RoundlyConsulting\Shops\Orders\Order;

/**
 * Contract a host application's shipping method implements. The package ships
 * only free shipping; bind your own carrier via `shops.shipping.method`.
 */
interface ShippingMethod
{
    public function quote(Order $order, Address $destination): Money;

    public function label(): string;
}
