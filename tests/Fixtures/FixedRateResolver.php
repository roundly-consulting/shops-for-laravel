<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Tests\Fixtures;

use RoundlyConsulting\Shops\Contracts\TaxResolver;
use RoundlyConsulting\Shops\Shops\Shop;
use RoundlyConsulting\Shops\Support\Tax\TaxRateValue;

/**
 * A deterministic resolver returning a fixed basis-point rate, used to assert
 * Price tax math without touching the database or config.
 */
final readonly class FixedRateResolver implements TaxResolver
{
    public function __construct(private int $basisPoints) {}

    public function rateFor(
        ?Shop $shop,
        string $taxClass = 'standard',
        ?string $country = null,
    ): TaxRateValue {
        return new TaxRateValue($this->basisPoints, $taxClass, $country);
    }
}
