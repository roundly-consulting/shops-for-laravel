<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Contracts;

use RoundlyConsulting\Shops\Orders\DataTransferObjects\Address;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Support\Money\Money;

/**
 * Contract a host application's shipping method implements. The package ships
 * only free shipping; bind your own carrier via `shops.shipping.method`.
 */
interface ShippingMethod
{
    public function quote(Order $order, Address $destination): Money;

    public function label(): string;
}
