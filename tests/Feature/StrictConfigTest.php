<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Shops\Actions\Inventory\AdjustStockAction;
use RoundlyConsulting\Shops\Inventory\Enums\StockReason;
use RoundlyConsulting\Shops\Inventory\Events\StockRanLow;
use RoundlyConsulting\Shops\Orders\Enums\PriceType;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Products\Category;
use RoundlyConsulting\Shops\Products\Product;
use RoundlyConsulting\Shops\Products\ProductVariant;
use RoundlyConsulting\Shops\ShopsServiceProvider;
use RoundlyConsulting\Shops\Support\ShopsConfig;
use RoundlyConsulting\Shops\Support\Tax\ConfigTaxResolver;

/*
 | Owner rule: a typo in a host's config fails loudly and never falls back silently. A junk
 | tax rate used to cast to 0 % and a junk low-stock threshold to 0; a `price_type` typo was a
 | raw ValueError; junk media widths were dropped and a junk byte cap ignored.
 */

it('refuses a junk tax rate instead of charging 0 % (strict config)', function (mixed $rate): void {
    config()->set('shops.tax_classes', ['standard' => $rate]);

    expect(fn () => (new ConfigTaxResolver)->rateFor(null, 'standard'))
        ->toThrow(InvalidConfigurationException::class, '[shops.tax_classes.standard]');
})->with([
    'word' => 'twenty',
    'decimal' => '19.5',
    'empty' => '',
    'over 100 %' => 101,
    'negative' => '-1',
]);

it('reads a canonical integer-string tax rate from env (strict config)', function (): void {
    config()->set('shops.tax_classes', ['standard' => '21', 'reduced' => 10]);

    expect((new ConfigTaxResolver)->rateFor(null, 'standard')->basisPoints)->toBe(2100)
        ->and((new ConfigTaxResolver)->rateFor(null, 'unknown')->basisPoints)->toBe(2100)
        ->and((new ConfigTaxResolver)->rateFor(null, 'reduced')->basisPoints)->toBe(1000);
});

it('refuses a non-array tax class map (strict config)', function (): void {
    config()->set('shops.tax_classes', '20');

    expect(fn () => (new ConfigTaxResolver)->rateFor(null))
        ->toThrow(InvalidConfigurationException::class, '[shops.tax_classes]');
});

it('refuses a price type typo instead of a raw ValueError (strict config)', function (): void {
    config()->set('shops.pricing.price_type', 'gros');

    expect(fn () => Order::factory()->create(['price_type' => null]))
        ->toThrow(InvalidConfigurationException::class, 'Configuration value [shops.pricing.price_type] must be one of [net, gross], [gros] given.');
});

it('prices gross only when the price type is absent (strict config)', function (): void {
    config()->set('shops.pricing.price_type', null);

    expect(Order::factory()->create(['price_type' => null])->price_type)->toBe(PriceType::Gross);
});

it('refuses a blank or wrong-typed default currency (strict config)', function (mixed $currency): void {
    config()->set('shops.pricing.default_currency', $currency);

    expect(fn () => Order::factory()->create(['currency' => null]))
        ->toThrow(InvalidConfigurationException::class, '[shops.pricing.default_currency]');
})->with(['empty' => '', 'array' => [['EUR']], 'int' => 978]);

it('refuses a junk low-stock threshold instead of casting it to 0 (strict config)', function (mixed $threshold): void {
    config()->set('shops.inventory.low_stock_threshold', $threshold);
    $variant = ProductVariant::factory()->create(['stock' => 10]);

    expect(fn () => app(AdjustStockAction::class)->execute($variant, -1, StockReason::Sold))
        ->toThrow(InvalidConfigurationException::class, '[shops.inventory.low_stock_threshold]');
})->with(['five', '5.5', -1]);

it('fires StockRanLow at a canonical integer-string threshold (strict config)', function (): void {
    Event::fake([StockRanLow::class]);
    config()->set('shops.inventory.low_stock_threshold', '5');
    $variant = ProductVariant::factory()->create(['stock' => 6]);

    app(AdjustStockAction::class)->execute($variant, -1, StockReason::Sold);

    Event::assertDispatched(StockRanLow::class, fn (StockRanLow $event): bool => $event->threshold === 5);
});

it('refuses a blank or wrong-typed bucket, disk or locale (strict config)', function (string $key, mixed $value, Closure $read): void {
    config()->set($key, $value);

    expect($read)->toThrow(InvalidConfigurationException::class, "[{$key}]");
})->with([
    'featured bucket' => ['shops.media.featured_bucket', '', fn () => (new Product)->featuredBucket()],
    'gallery bucket' => ['shops.media.gallery_bucket', ['gallery'], fn () => (new Product)->galleryBucket()],
    'variant bucket' => ['shops.media.variant_bucket', ' ', fn () => (new ProductVariant)->variantGalleryBucket()],
    'banner bucket' => ['shops.media.banner_bucket', 1, fn () => (new Category)->bannerBucket()],
    'media disk' => ['shops.media.disk', '', fn () => Product::factory()->create()->addMedia(UploadedFile::fake()->image('a.jpg', 8, 8))->toMediaBucket('gallery')],
    'fallback locale' => ['shops.locales.fallback', '', fn () => Product::factory()->create()],
]);

it('refuses junk media widths or byte cap instead of dropping them (strict config)', function (string $key, mixed $value): void {
    config()->set($key, $value);

    expect(fn () => Product::factory()->create()->addMedia(UploadedFile::fake()->image('a.jpg', 8, 8))->toMediaBucket('gallery'))
        ->toThrow(InvalidConfigurationException::class, "[{$key}");
})->with([
    'widths not a list' => ['shops.media.responsive_widths', '320,640'],
    'a junk width' => ['shops.media.responsive_widths', [320, 'wide']],
    'a zero width' => ['shops.media.responsive_widths', [0]],
    'a junk byte cap' => ['shops.media.max_file_size', '1MB'],
    'a zero byte cap' => ['shops.media.max_file_size', 0],
]);

it('refuses a malformed product attribute definition instead of skipping it (strict config)', function (mixed $definitions): void {
    config()->set('shops.attributes.definitions', $definitions);

    $provider = new ShopsServiceProvider(app());
    $provider->register();

    expect(fn () => $provider->boot())
        ->toThrow(InvalidConfigurationException::class, '[shops.attributes.definitions');
})->with([
    'a string' => ['colour:string'],
    'a non-array entry' => [['colour' => 'string']],
]);

it('names the shops key when a product attribute definition is invalid (strict config)', function (string $leaf, mixed $value): void {
    // The definitions are parsed by attributes' DefinitionFactory, whose messages default to
    // `attributes.definitions.<name>` — a key this host never set. They must name the shops one.
    config()->set('shops.attributes.definitions', ['colour' => [$leaf => $value]]);

    $provider = new ShopsServiceProvider(app());
    $provider->register();

    expect(fn () => $provider->boot())
        ->toThrow(InvalidConfigurationException::class, "[shops.attributes.definitions.colour.{$leaf}]");
})->with([
    'a type typo' => ['type', 'strng'],
    'a junk required flag' => ['required', 'maybe'],
    'non-array rules' => ['rules', 'required|string'],
]);

it('reports a broken setting as INVALID in about (strict config)', function (): void {
    config()->set('shops.pricing.price_type', 'gros');
    config()->set('shops.inventory.low_stock_threshold', 'five');
    config()->set('shops.locales.fallback', '');
    config()->set('shops.media.responsive_widths', 'wide');

    Artisan::call('about', ['--only' => 'shops', '--json' => true]);

    /** @var array{shops: array<string, string>} $about */
    $about = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($about['shops'])
        ->price_type->toBe('INVALID')
        ->low_stock_threshold->toBe('INVALID')
        ->fallback_locale->toBe('INVALID')
        ->catalog_media->toBe('INVALID');
});

it('takes the documented defaults only for absent keys (strict config)', function (): void {
    foreach (['shops.media.responsive_widths', 'shops.media.max_file_size', 'shops.media.disk', 'shops.locales.fallback'] as $key) {
        config()->set($key, null);
    }
    config()->set('app.fallback_locale', 'de');

    expect(ShopsConfig::responsiveWidths())->toBeNull()
        ->and(ShopsConfig::maxFileSize())->toBeNull()
        ->and(ShopsConfig::mediaDisk())->toBeNull()
        ->and(ShopsConfig::fallbackLocale())->toBe('de');

    config()->set('app.fallback_locale', null);

    expect(ShopsConfig::fallbackLocale())->toBe('en');
});
