<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Support\Tax;

/**
 * An immutable resolved tax rate. Rates are stored as integer basis points
 * (1 basis point = 0.01%), keeping the same exact-integer discipline the package
 * uses for money: 19% is 1900, 8.5% is 850, 0% is 0.
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

    /**
     * The percentage as a float, e.g. 19.0 or 8.5.
     */
    public function percent(): float
    {
        return $this->basisPoints / 100;
    }

    /**
     * The divisor used to extract net from a gross (tax-inclusive) amount:
     * 1 + rate, e.g. 1.19 for 19% or 1.085 for 8.5%.
     */
    public function grossDivisor(): float
    {
        return 1 + ($this->basisPoints / 10000);
    }

    public function isZero(): bool
    {
        return $this->basisPoints === 0;
    }
}
