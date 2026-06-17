<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Actions;

use RoundlyConsulting\Shops\Contracts\ShippingMethod;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Address;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Support\Money\Money;

/**
 * Quotes shipping cost for an order to a destination through the configured
 * shipping method.
 */
final class QuoteShippingAction
{
    public function __construct(
        private readonly ShippingMethod $method,
    ) {}

    public function execute(Order $order, Address $destination): Money
    {
        return $this->method->quote($order, $destination);
    }
}
