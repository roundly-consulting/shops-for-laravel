<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Shops\Products\Product;
use RoundlyConsulting\Shops\Shops\CurrentShop;
use RoundlyConsulting\Shops\Shops\Shop;
use RoundlyConsulting\Shops\Shops\TaxRate;
use RoundlyConsulting\Shops\Tests\Fixtures\Customer;
use RoundlyConsulting\Shops\Tests\Fixtures\CustomShop;

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

    config()->set('shops.shop_model', CustomShop::class);

    expect(Shop::resolveModelClass())->toBe(CustomShop::class);
});

it('routes owned records and the current shop through the configured model', function (): void {
    config()->set('shops.shop_model', CustomShop::class);

    $shop = CustomShop::query()->create(['name' => ['en' => 'Acme'], 'slug' => ['en' => 'acme']]);

    app(CurrentShop::class)->set($shop);

    $product = Product::factory()->create();

    // The relation, the current-shop context and the tax-rate owner all land on the
    // host's model, not the packaged one.
    expect($product->shop_id)->toBe($shop->getKey())
        ->and($product->shop)->toBeInstanceOf(CustomShop::class)
        ->and(Shop::current())->toBeInstanceOf(CustomShop::class)
        ->and(app(CurrentShop::class)->get())->toBeInstanceOf(CustomShop::class);

    $rate = TaxRate::factory()->for($shop, 'shop')->create();

    expect($rate->shop)->toBeInstanceOf(CustomShop::class);
});

/**
 * Unlike the package's other configurable models, the tenant key is deliberately
 * NOT narrowed to Shop: a host may point it at a tenant model of its own that has
 * no reason to extend ours — an owned record only ever needs its primary key.
 */
it('honours a tenant model that does not extend the packaged shop', function (): void {
    config()->set('shops.shop_model', Customer::class);

    expect(Shop::resolveModelClass())->toBe(Customer::class);
});

it('rejects a shop model that is not an eloquent model', function (): void {
    config()->set('shops.shop_model', 'App\\Models\\NotARealClass');

    expect(fn () => Shop::resolveModelClass())->toThrow(InvalidConfigurationException::class);
});

it('exposes its tax rates through the relation', function (): void {
    $shop = Shop::factory()->create();
    $rate = TaxRate::factory()->for($shop, 'shop')->create();

    expect($shop->taxRates()->pluck('id')->all())->toBe([$rate->id]);
});
