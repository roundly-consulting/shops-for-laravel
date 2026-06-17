<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Contracts;

/**
 * Resolves the whole-number tax rate (e.g. 20 for 20%) that applies to a given
 * tax class. A host application may bind its own implementation via
 * `config('shops.tax.resolver')` to add jurisdiction-aware logic; the package
 * ships a config-driven default.
 */
interface TaxResolver
{
    public function rateFor(string $taxClass): int;
}
