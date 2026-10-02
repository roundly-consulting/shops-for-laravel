<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Support\Tax;

use Illuminate\Support\Collection;
use RoundlyConsulting\Shops\Contracts\TaxResolver;
use RoundlyConsulting\Shops\Shops\Shop;
use RoundlyConsulting\Shops\Shops\TaxRate;

/**
 * The default tax resolver. Resolves a (shop, class, country) lookup against the
 * shop's database tax rates with a defined fallback chain:
 *
 *   1. exact match: shop + class + the requested country (case-insensitive — an address
 *      country is user input)
 *   2. the shop's class default: the `is_default` rate, a country-agnostic one first, else a
 *      country-specific one (a German shop's DE rate as its default for everywhere else)
 *   3. the highest-priority rate for the class within the shop
 *   4. the config floor (`shops.tax_classes`) via {@see ConfigTaxResolver}
 *   5. a zero rate
 *
 * Ties are broken by `priority` descending then `id` ascending. The shop's rates
 * are fetched in one query and picked in PHP.
 */
final readonly class DatabaseTaxResolver implements TaxResolver
{
    public function __construct(
        private ConfigTaxResolver $config = new ConfigTaxResolver,
    ) {}

    public function rateFor(
        ?Shop $shop,
        string $taxClass = 'standard',
        ?string $country = null,
    ): TaxRateValue {
        if ($shop === null) {
            return $this->config->rateFor($shop, $taxClass, $country);
        }

        $rates = $this->ratesFor($shop, $taxClass);
        $country = self::normalizeCountry($country);

        $match = $this->exactCountryMatch($rates, $country)
            ?? $this->classDefault($rates)
            ?? $rates->first();

        if ($match instanceof TaxRate) {
            return $match->toValue();
        }

        return $this->config->rateFor($shop, $taxClass, $country);
    }

    /**
     * @return Collection<int, TaxRate>
     */
    private function ratesFor(Shop $shop, string $taxClass): Collection
    {
        /** @var Collection<int, TaxRate> $rates */
        $rates = TaxRate::query()
            ->where('shop_id', $shop->getKey())
            ->where('tax_class', $taxClass)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        return $rates;
    }

    /**
     * @param  Collection<int, TaxRate>  $rates
     */
    private function exactCountryMatch(Collection $rates, ?string $country): ?TaxRate
    {
        if ($country === null) {
            return null;
        }

        return $rates->first(
            fn (TaxRate $rate): bool => self::normalizeCountry($rate->country) === $country,
        );
    }

    /**
     * @param  Collection<int, TaxRate>  $rates
     */
    private function classDefault(Collection $rates): ?TaxRate
    {
        return $rates->first(fn (TaxRate $rate): bool => $rate->is_default && $rate->country === null)
            ?? $rates->first(fn (TaxRate $rate): bool => $rate->is_default);
    }

    /**
     * Upper-cased and trimmed, `null` for none: `de`, ` DE ` and `DE` are one country.
     */
    public static function normalizeCountry(?string $country): ?string
    {
        if ($country === null) {
            return null;
        }

        $country = mb_strtoupper(trim($country));

        return $country === '' ? null : $country;
    }
}
