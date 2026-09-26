<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Support\Tax;

use RoundlyConsulting\Money\Percentage;
use RoundlyConsulting\Money\Tax\TaxRate;

/**
 * An immutable resolved tax rate with its shop context (tax class, country, label). Rates
 * are integer basis points (1 bp = 0.01 %): 19 % is 1900, 8.5 % is 850, 0 % is 0. The tax
 * math itself is money-for-laravel's exact {@see TaxRate}.
 */
final readonly class TaxRateValue
{
    public function __construct(
        public int $basisPoints,
        public string $taxClass = 'standard',
        public ?string $country = null,
        public ?string $label = null,
    ) {}

    public static function zero(string $taxClass = 'standard'): self
    {
        return new self(0, $taxClass);
    }

    /** The money TaxRate that computes tax on net / in gross amounts exactly. */
    public function toTaxRate(): TaxRate
    {
        return TaxRate::fromBasisPoints($this->basisPoints, $this->label);
    }

    public function percentage(): Percentage
    {
        return Percentage::fromBasisPoints($this->basisPoints);
    }

    public function isZero(): bool
    {
        return $this->basisPoints === 0;
    }
}
