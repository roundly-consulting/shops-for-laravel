<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Support\Tax;

use RoundlyConsulting\Shops\Contracts\TaxResolver;

/**
 * Default tax resolver that reads whole-number rates from the
 * `shops.tax_classes` config map. Unknown classes fall back to the `standard`
 * class, and a missing `standard` entry falls back to zero.
 */
final class ConfigTaxResolver implements TaxResolver
{
    public function rateFor(string $taxClass): int
    {
        /** @var array<string, int|string> $classes */
        $classes = config('shops.tax_classes', []);

        if (array_key_exists($taxClass, $classes)) {
            return (int) $classes[$taxClass];
        }

        return (int) ($classes['standard'] ?? 0);
    }
}
