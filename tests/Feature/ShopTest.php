<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Shops\Shop;
use RoundlyConsulting\Shops\Shops\TaxRate;

it('creates a shop with translatable name, slug, and a route key', function (): void {
    $shop = Shop::create(['name' => 'Acme EU']);

    expect($shop->name)->toBe('Acme EU')
        ->and($shop->slug)->toBe('acme-eu')
        ->and($shop->getRouteKeyName())->toBe('slug')
        ->and(Shop::query()->whereKey($shop->getKey())->exists())->toBeTrue();
});

it('returns the per-shop currency override when set', function (): void {
    $shop = Shop::factory()->currency('USD')->create();

    expect($shop->currency())->toBe('USD');
});

it('falls back to the configured default currency when no override is set', function (): void {
    config()->set('shops.pricing.default_currency', 'GBP');

    $shop = Shop::factory()->create(['currency' => null]);

    expect($shop->currency())->toBe('GBP');
});

it('resolves the configured model class and honours an override', function (): void {
    expect(Shop::resolveModelClass())->toBe(Shop::class);

    config()->set('shops.shop_model', 'App\\Models\\CustomShop');

    expect(Shop::resolveModelClass())->toBe('App\\Models\\CustomShop');
});

it('exposes its tax rates through the relation', function (): void {
    $shop = Shop::factory()->create();
    $rate = TaxRate::factory()->for($shop, 'shop')->create();

    expect($shop->taxRates()->pluck('id')->all())->toBe([$rate->id]);
});
