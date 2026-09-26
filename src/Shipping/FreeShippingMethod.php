<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Shipping;

use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Contracts\ShippingMethod;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Address;
use RoundlyConsulting\Shops\Orders\Order;

/**
 * Default shipping method that always quotes zero. Replace it with a real
 * carrier implementation via `shops.shipping.method`.
 */
final class FreeShippingMethod implements ShippingMethod
{
    public function quote(Order $order, Address $destination): Money
    {
        return Money::zero($order->currency);
    }

    public function label(): string
    {
        return 'Free shipping';
    }
}
