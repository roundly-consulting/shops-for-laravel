<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Shipping;

use RoundlyConsulting\Shops\Contracts\ShippingMethod;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Address;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Support\Money\Money;

/**
 * Default shipping method that always quotes zero. Replace it with a real
 * carrier implementation via `shops.shipping.method`.
 */
final class FreeShippingMethod implements ShippingMethod
{
    public function quote(Order $order, Address $destination): Money
    {
        return Money::zero((string) config('shops.pricing.default_currency', 'EUR'));
    }

    public function label(): string
    {
        return 'Free shipping';
    }
}
