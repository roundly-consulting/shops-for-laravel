<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Support\Tax;

use RoundlyConsulting\Shops\Contracts\TaxResolver;
use RoundlyConsulting\Shops\Shops\Shop;
use RoundlyConsulting\Shops\Support\ShopsConfig;

/**
 * Tax resolver that reads whole-number rates from the `shops.tax_classes` config
 * map and returns them as basis points (a config `20` becomes 2000 bp). It
 * ignores the shop and country — every shop gets the same configured rate.
 * Unknown classes fall back to the `standard` class, and a missing `standard`
 * entry falls back to zero. A configured rate must be a whole number 0..100 (or
 * its canonical string): `'twenty'`, `'19.5'` or `''` throw an
 * InvalidConfigurationException naming the class — never a silent 0 % rate. Used directly, or as the fallback floor for
 * {@see DatabaseTaxResolver}.
 */
final class ConfigTaxResolver implements TaxResolver
{
    public function rateFor(
        ?Shop $shop,
        string $taxClass = 'standard',
        ?string $country = null,
    ): TaxRateValue {
        $classes = ShopsConfig::taxRates();

        $percent = $classes[$taxClass] ?? $classes['standard'] ?? 0;

        return new TaxRateValue(
            basisPoints: $percent * 100,
            taxClass: $taxClass,
            country: $country,
        );
    }
}
