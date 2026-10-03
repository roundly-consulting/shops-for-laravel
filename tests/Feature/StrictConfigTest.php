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
 | raw ValueError; junk media widths were dropped and a junk byte cap ignored. A blank value
 | (`''` or whitespace — a host's `KEY=`) is not junk: it is not set, so the default applies.
 */

it('refuses a junk tax rate instead of charging 0 % (strict config)', function (mixed $rate): void {
    config()->set('shops.tax_classes', ['standard' => $rate]);

    expect(fn () => (new ConfigTaxResolver)->rateFor(null, 'standard'))
        ->toThrow(InvalidConfigurationException::class, '[shops.tax_classes.standard]');
})->with([
    'word' => 'twenty',
    'decimal' => '19.5',
    'over 100 %' => 101,
    'over 100 % string' => '101',
    'negative' => '-1',
]);

it('reads a standard tax rate that is not set as the shipped 20 %, never 0 % (strict config)', function (array $classes): void {
    // A host's `SHOPS_TAX_RATE=` line is blank, not "no tax": not set — blank, null or left out —
    // takes the shipped 20 %, the same rate the shipped config file declares.
    config()->set('shops.tax_classes', $classes);

    expect((new ConfigTaxResolver)->rateFor(null, 'standard')->basisPoints)->toBe(2000)
        ->and((new ConfigTaxResolver)->rateFor(null, 'unknown')->basisPoints)->toBe(2000);
})->with([
    'blank' => [['standard' => '']],
    'whitespace' => [['standard' => '  ']],
    'null' => [['standard' => null]],
    'absent' => [['reduced' => 10]],
]);

it('reads every shipped tax class that is not set as its shipped rate (strict config)', function (mixed $notSet): void {
    config()->set('shops.tax_classes', ['standard' => $notSet, 'reduced' => $notSet, 'zero' => $notSet]);

    expect(ShopsConfig::taxRates())->toBe(['standard' => 20, 'reduced' => 10, 'zero' => 0]);
})->with(['blank' => '', 'whitespace' => '  ', 'null' => null]);

it('falls a host tax class that is not set back to the standard rate (strict config)', function (mixed $notSet): void {
    config()->set('shops.tax_classes', ['standard' => 19, 'luxury' => $notSet]);

    expect(ShopsConfig::taxRates())->toBe(['standard' => 19, 'reduced' => 10, 'zero' => 0])
        ->and((new ConfigTaxResolver)->rateFor(null, 'luxury')->basisPoints)->toBe(1900);
})->with(['blank' => '', 'whitespace' => '  ', 'null' => null]);

it('keeps an explicit 0 % standard rate (strict config)', function (int|string $zero): void {
    config()->set('shops.tax_classes', ['standard' => $zero]);

    expect((new ConfigTaxResolver)->rateFor(null, 'standard')->isZero())->toBeTrue();
})->with(['integer' => 0, 'env string' => '0']);

it('defaults every tax class to the rate the shipped config file declares (strict config)', function (): void {
    /** @var array{tax_classes: array<string, mixed>} $shipped */
    $shipped = require __DIR__.'/../../config/shops.php';

    config()->set('shops.tax_classes', []);

    expect(ShopsConfig::taxRates())->toBe($shipped['tax_classes']);
});

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

it('refuses a wrong-typed default currency (strict config)', function (mixed $currency): void {
    config()->set('shops.pricing.default_currency', $currency);

    expect(fn () => Order::factory()->create(['currency' => null]))
        ->toThrow(InvalidConfigurationException::class, '[shops.pricing.default_currency]');
})->with(['array' => [['EUR']], 'int' => 978]);

it('prices in EUR and gross when the currency and price type are blank (strict config)', function (string $blank): void {
    config()->set('shops.pricing.default_currency', $blank);
    config()->set('shops.pricing.price_type', $blank);

    $order = Order::factory()->create(['currency' => null, 'price_type' => null]);

    expect($order->currency->code)->toBe('EUR')
        ->and($order->price_type)->toBe(PriceType::Gross);
})->with(['empty' => '', 'whitespace' => '  ']);

it('refuses a junk low-stock threshold instead of casting it to 0 (strict config)', function (mixed $threshold): void {
    config()->set('shops.inventory.low_stock_threshold', $threshold);
    $variant = ProductVariant::factory()->create(['stock' => 10]);

    expect(fn () => app(AdjustStockAction::class)->execute($variant, -1, StockReason::Sold))
        ->toThrow(InvalidConfigurationException::class, '[shops.inventory.low_stock_threshold]');
})->with(['five', '5.5', -1]);

it('reads a blank low-stock threshold as the 0 default (strict config)', function (): void {
    config()->set('shops.inventory.low_stock_threshold', '');

    expect(ShopsConfig::lowStockThreshold())->toBe(0);
});

it('fires StockRanLow at a canonical integer-string threshold (strict config)', function (): void {
    Event::fake([StockRanLow::class]);
    config()->set('shops.inventory.low_stock_threshold', '5');
    $variant = ProductVariant::factory()->create(['stock' => 6]);

    app(AdjustStockAction::class)->execute($variant, -1, StockReason::Sold);

    Event::assertDispatched(StockRanLow::class, fn (StockRanLow $event): bool => $event->threshold === 5);
});

it('refuses a wrong-typed bucket, disk or locale (strict config)', function (string $key, mixed $value, Closure $read): void {
    config()->set($key, $value);

    expect($read)->toThrow(InvalidConfigurationException::class, "[{$key}]");
})->with([
    'featured bucket' => ['shops.media.featured_bucket', ['featured'], fn () => (new Product)->featuredBucket()],
    'gallery bucket' => ['shops.media.gallery_bucket', ['gallery'], fn () => (new Product)->galleryBucket()],
    'variant bucket' => ['shops.media.variant_bucket', 5, fn () => (new ProductVariant)->variantGalleryBucket()],
    'banner bucket' => ['shops.media.banner_bucket', 1, fn () => (new Category)->bannerBucket()],
    'media disk' => ['shops.media.disk', ['s3'], fn () => Product::factory()->create()->addMedia(UploadedFile::fake()->image('a.jpg', 8, 8))->toMediaBucket('gallery')],
    'fallback locale' => ['shops.locales.fallback', ['en'], fn () => Product::factory()->create()],
]);

it('reads blank buckets, disk, locale and media limits as not set (strict config)', function (string $blank): void {
    foreach ([
        'shops.media.featured_bucket', 'shops.media.gallery_bucket', 'shops.media.variant_bucket',
        'shops.media.banner_bucket', 'shops.media.disk', 'shops.media.responsive_widths',
        'shops.media.max_file_size', 'shops.locales.fallback', 'shops.payment.store_credit_bucket',
        'shops.tax_classes', 'shops.attributes.definitions',
    ] as $key) {
        config()->set($key, $blank);
    }
    config()->set('app.fallback_locale', 'de');

    expect((new Product)->featuredBucket())->toBe('featured')
        ->and((new Product)->galleryBucket())->toBe('gallery')
        ->and((new ProductVariant)->variantGalleryBucket())->toBe('gallery')
        ->and((new Category)->bannerBucket())->toBe('banner')
        ->and(ShopsConfig::mediaDisk())->toBeNull()
        ->and(ShopsConfig::responsiveWidths())->toBeNull()
        ->and(ShopsConfig::maxFileSize())->toBeNull()
        ->and(ShopsConfig::fallbackLocale())->toBe('de')
        ->and(ShopsConfig::storeCreditBucket())->toBe('store_credit')
        ->and(ShopsConfig::taxRates())->toBe(['standard' => 20, 'reduced' => 10, 'zero' => 0])
        ->and(ShopsConfig::attributeDefinitions())->toBe([]);

    config()->set('app.fallback_locale', $blank);

    expect(ShopsConfig::fallbackLocale())->toBe('en');
})->with(['empty' => '', 'whitespace' => '  ']);

it('refuses junk media widths or byte cap instead of dropping them (strict config)', function (string $key, mixed $value): void {
    config()->set($key, $value);

    expect(fn () => Product::factory()->create()->addMedia(UploadedFile::fake()->image('a.jpg', 8, 8))->toMediaBucket('gallery'))
        ->toThrow(InvalidConfigurationException::class, "[{$key}");
})->with([
    'widths not a list' => ['shops.media.responsive_widths', '320,640'],
    'a junk width' => ['shops.media.responsive_widths', [320, 'wide']],
    'a zero width' => ['shops.media.responsive_widths', [0]],
    'a blank width' => ['shops.media.responsive_widths', [320, '']],
    'a null width' => ['shops.media.responsive_widths', [null]],
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
    config()->set('shops.locales.fallback', ['en']);
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
