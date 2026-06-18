<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Contracts;

use RoundlyConsulting\Shops\Shops\Shop;
use RoundlyConsulting\Shops\Support\Tax\TaxRateValue;

/**
 * Resolves the tax rate that applies to a given shop, tax class, and optional
 * destination country. A host application may bind its own implementation via
 * `config('shops.tax.resolver')` to add jurisdiction-aware logic; the package
 * ships a database-backed resolver with a config-driven fallback.
 */
interface TaxResolver
{
    /**
     * Resolve the applicable tax rate for a shop, tax class, and optional
     * destination country (ISO-3166-1 alpha-2). Implementations MUST return a
     * value object (never null) — an unmatched lookup resolves to a zero rate.
     */
    public function rateFor(
        ?Shop $shop,
        string $taxClass = 'standard',
        ?string $country = null,
    ): TaxRateValue;
}
