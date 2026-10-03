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
 * Unknown classes fall back to the `standard` class. A configured rate must be a
 * whole number 0..100 (or its canonical string): `'twenty'` or `'19.5'` throw an
 * InvalidConfigurationException naming the class — never a silent 0 % rate. A
 * shipped class whose rate is not set (absent, null or blank) takes its shipped
 * rate — `standard` 20 %, `reduced` 10 %, `zero` 0 % — so a blank
 * `SHOPS_TAX_RATE=` never charges 0 %. Used directly, or as the fallback floor for
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

        $percent = $classes[$taxClass] ?? $classes['standard'];

        return new TaxRateValue(
            basisPoints: $percent * 100,
            taxClass: $taxClass,
            country: $country,
        );
    }
}
