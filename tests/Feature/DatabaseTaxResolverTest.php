<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Contracts\TaxResolver;
use RoundlyConsulting\Shops\Shops\Shop;
use RoundlyConsulting\Shops\Shops\TaxRate;
use RoundlyConsulting\Shops\Support\Tax\DatabaseTaxResolver;

it('returns the exact (shop, class, country) match when present', function (): void {
    $shop = Shop::factory()->create();
    TaxRate::factory()->for($shop, 'shop')->create([
        'tax_class' => 'standard', 'country' => 'DE', 'rate' => 1900,
    ]);
    TaxRate::factory()->for($shop, 'shop')->default()->create([
        'tax_class' => 'standard', 'country' => null, 'rate' => 2100,
    ]);

    $value = app(DatabaseTaxResolver::class)->rateFor($shop, 'standard', 'DE');

    expect($value->basisPoints)->toBe(1900);
});

it('breaks exact-match ties by priority then id', function (): void {
    $shop = Shop::factory()->create();
    TaxRate::factory()->for($shop, 'shop')->create([
        'tax_class' => 'standard', 'country' => 'DE', 'rate' => 1900, 'priority' => 1,
    ]);
    TaxRate::factory()->for($shop, 'shop')->create([
        'tax_class' => 'standard', 'country' => 'DE', 'rate' => 1700, 'priority' => 5,
    ]);

    expect(app(DatabaseTaxResolver::class)->rateFor($shop, 'standard', 'DE')->basisPoints)->toBe(1700);
});

it('falls back to the class default when no country matches', function (): void {
    $shop = Shop::factory()->create();
    TaxRate::factory()->for($shop, 'shop')->create([
        'tax_class' => 'standard', 'country' => 'FR', 'rate' => 2000,
    ]);
    TaxRate::factory()->for($shop, 'shop')->default()->create([
        'tax_class' => 'standard', 'country' => null, 'rate' => 1900,
    ]);

    expect(app(DatabaseTaxResolver::class)->rateFor($shop, 'standard', 'DE')->basisPoints)->toBe(1900);
});

it('falls back to the highest-priority class rate when there is no default', function (): void {
    $shop = Shop::factory()->create();
    TaxRate::factory()->for($shop, 'shop')->create([
        'tax_class' => 'standard', 'country' => null, 'rate' => 1800, 'priority' => 1,
    ]);
    TaxRate::factory()->for($shop, 'shop')->create([
        'tax_class' => 'standard', 'country' => null, 'rate' => 1900, 'priority' => 9,
    ]);

    expect(app(DatabaseTaxResolver::class)->rateFor($shop, 'standard')->basisPoints)->toBe(1900);
});

it('falls back to the config floor when the shop has no rate for the class', function (): void {
    $shop = Shop::factory()->create();

    expect(app(DatabaseTaxResolver::class)->rateFor($shop, 'standard')->basisPoints)->toBe(2000);
});

it('uses the config floor directly when no shop is given', function (): void {
    expect(app(DatabaseTaxResolver::class)->rateFor(null, 'reduced')->basisPoints)->toBe(1000);
});

it('resolves to zero when neither database nor config provide a rate', function (): void {
    config()->set('shops.tax_classes', []);
    $shop = Shop::factory()->create();

    expect(app(DatabaseTaxResolver::class)->rateFor($shop, 'standard')->isZero())->toBeTrue();
});

it('is constructable from the container and is the bound default resolver', function (): void {
    expect(app(DatabaseTaxResolver::class))->toBeInstanceOf(DatabaseTaxResolver::class)
        ->and(resolve(TaxResolver::class))->toBeInstanceOf(DatabaseTaxResolver::class);
});

it('matches the destination country case-insensitively', function (string $asked): void {
    $shop = Shop::factory()->create();
    TaxRate::factory()->for($shop, 'shop')->create([
        'tax_class' => 'standard', 'country' => 'DE', 'rate' => 1900,
    ]);
    TaxRate::factory()->for($shop, 'shop')->create([
        'tax_class' => 'standard', 'country' => 'AT', 'rate' => 2000, 'priority' => 9,
    ]);

    expect(app(DatabaseTaxResolver::class)->rateFor($shop, 'standard', $asked)->basisPoints)->toBe(1900);
})->with(['de', 'De', ' DE ']);

it('stores a rate country upper-cased, so a lowercase row still matches', function (): void {
    $shop = Shop::factory()->create();
    $rate = TaxRate::factory()->for($shop, 'shop')->create([
        'tax_class' => 'standard', 'country' => 'de', 'rate' => 1900,
    ]);

    expect($rate->country)->toBe('DE')
        ->and(app(DatabaseTaxResolver::class)->rateFor($shop, 'standard', 'DE')->basisPoints)->toBe(1900);
});

it('honours a country-specific rate flagged as the class default', function (): void {
    $shop = Shop::factory()->create();
    TaxRate::factory()->for($shop, 'shop')->default()->create([
        'tax_class' => 'standard', 'country' => 'DE', 'rate' => 1900,
    ]);
    TaxRate::factory()->for($shop, 'shop')->create([
        'tax_class' => 'standard', 'country' => 'AT', 'rate' => 2000, 'priority' => 9,
    ]);

    // No destination (a cart), or one the shop has no rate for: the flagged default wins over
    // the higher-priority AT rate.
    expect(app(DatabaseTaxResolver::class)->rateFor($shop, 'standard')->basisPoints)->toBe(1900)
        ->and(app(DatabaseTaxResolver::class)->rateFor($shop, 'standard', 'IT')->basisPoints)->toBe(1900)
        ->and(app(DatabaseTaxResolver::class)->rateFor($shop, 'standard', 'AT')->basisPoints)->toBe(2000);
});

it('prefers a country-agnostic default over a country-specific one', function (): void {
    $shop = Shop::factory()->create();
    TaxRate::factory()->for($shop, 'shop')->default()->create([
        'tax_class' => 'standard', 'country' => 'DE', 'rate' => 1900, 'priority' => 9,
    ]);
    TaxRate::factory()->for($shop, 'shop')->default()->create([
        'tax_class' => 'standard', 'country' => null, 'rate' => 2100,
    ]);

    expect(app(DatabaseTaxResolver::class)->rateFor($shop, 'standard', 'FR')->basisPoints)->toBe(2100);
});
