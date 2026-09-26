<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\DataTransferObjects;

use RoundlyConsulting\Money\Money;

/**
 * A single priced line going into a {@see Price} calculation: the unit price,
 * the quantity, and the tax class that determines its rate.
 */
final readonly class PriceLine
{
    public function __construct(
        public Money $unitPrice,
        public int $quantity = 1,
        public string $taxClass = 'standard',
    ) {}

    public function lineTotal(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }
}
