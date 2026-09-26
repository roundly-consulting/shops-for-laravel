<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\DataTransferObjects;

use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Exceptions\InvalidQuantityException;
use RoundlyConsulting\Shops\Support\Tax\TaxRateValue;

/**
 * A single priced line going into a {@see Price} calculation: the unit price,
 * the quantity, and the tax class that determines its rate — or, for a placed order's
 * line, the rate it was snapshotted at (which then wins over the resolver). A line holds at
 * least one item: a zero or negative quantity throws {@see InvalidQuantityException}.
 */
final readonly class PriceLine
{
    public function __construct(
        public Money $unitPrice,
        public int $quantity = 1,
        public string $taxClass = 'standard',
        public ?TaxRateValue $taxRate = null,
    ) {
        if ($quantity < 1) {
            throw InvalidQuantityException::notPositive($quantity);
        }
    }

    public function lineTotal(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }
}
