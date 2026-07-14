<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Shops\Contracts\DiscountResolver;
use RoundlyConsulting\Shops\Contracts\PaymentGateway;
use RoundlyConsulting\Shops\Contracts\ShippingMethod;
use RoundlyConsulting\Shops\Contracts\TaxResolver;
use RoundlyConsulting\Shops\Discounts\CouponPackageDiscountResolver;
use RoundlyConsulting\Shops\Orders\NumberGenerators\DefaultNumberGenerator;
use RoundlyConsulting\Shops\Orders\NumberGenerators\NumberGenerator;
use RoundlyConsulting\Shops\Payments\NullPaymentGateway;
use RoundlyConsulting\Shops\Reviews\Contracts\VerifiedPurchaseResolver;
use RoundlyConsulting\Shops\Reviews\NullVerifiedPurchaseResolver;
use RoundlyConsulting\Shops\Shipping\FreeShippingMethod;
use RoundlyConsulting\Shops\ShopManager;
use RoundlyConsulting\Shops\ShopsServiceProvider;
use RoundlyConsulting\Shops\Support\Tax\DatabaseTaxResolver;

it('binds the configured implementations', function (): void {
    expect(app(NumberGenerator::class))->toBeInstanceOf(DefaultNumberGenerator::class)
        ->and(app(TaxResolver::class))->toBeInstanceOf(DatabaseTaxResolver::class)
        ->and(app(PaymentGateway::class))->toBeInstanceOf(NullPaymentGateway::class)
        ->and(app(ShippingMethod::class))->toBeInstanceOf(FreeShippingMethod::class)
        ->and(app(DiscountResolver::class))->toBeInstanceOf(CouponPackageDiscountResolver::class)
        ->and(app(VerifiedPurchaseResolver::class))->toBeInstanceOf(NullVerifiedPurchaseResolver::class)
        ->and(app(ShopManager::class))->toBe(app(ShopManager::class));
});

it('publishes the config file under the shops-config tag', function (): void {
    $paths = ServiceProvider::pathsToPublish(ShopsServiceProvider::class, 'shops-config');

    expect(array_values($paths))->toBe([config_path('shops.php')]);
});

/**
 * Publish-only migrations (fleet policy). A bare `php artisan migrate` must not
 * create the package's tables — the host publishes them first.
 */
it('never auto-loads its migrations', function (): void {
    expect(app('migrator')->paths())
        ->not->toContain(realpath(__DIR__.'/../../database/migrations'));
});

it('publishes every migration timestamped under the shops-migrations tag', function (): void {
    $paths = ServiceProvider::pathsToPublish(ShopsServiceProvider::class, 'shops-migrations');

    expect($paths)->toHaveCount(14);

    $destinations = array_values($paths);

    foreach ($destinations as $destination) {
        expect(basename((string) $destination))
            ->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_\d{4}_create_[a-z_]+_table\.php$/');
    }

    // Publishing preserves the dependency order the sources are numbered in, so the
    // host's migrator runs shops → products → variants → carts → orders.
    $sorted = $destinations;
    sort($sorted);

    expect($sorted)->toBe($destinations)
        ->and(basename((string) $destinations[0]))->toContain('0001_create_shops_table')
        ->and(basename((string) $destinations[13]))->toContain('0014_create_tax_rates_table');
});

/**
 * Secret-safe `about`. A shop's config names the host's payment gateway and tax
 * resolver (its provider integrations), its storage disk, its store-credit ledger
 * bucket, and its product attribute keys — which are the host's own field names.
 * None of them may render.
 */
it('reports configuration without leaking host vocabulary', function (): void {
    config()->set('shops.payment.gateway', 'App\\Billing\\Acme\\LiveGateway');
    config()->set('shops.payment.allow_store_credit', true);
    config()->set('shops.payment.store_credit_bucket', 'acme-internal-ledger');
    config()->set('shops.media.disk', 's3-private-catalog');
    config()->set('shops.attributes.definitions', [
        'supplier_cost' => ['type' => 'integer'],
        'serial_number' => ['type' => 'string'],
    ]);
    config()->set('shops.tax_classes', ['eu-oss-reduced' => 10, 'acme-wholesale' => 0]);

    Artisan::call('about', ['--only' => 'shops']);

    $rendered = Artisan::output();

    // Guard the guard: an empty capture would make every assertion below vacuous.
    expect($rendered)->toContain('Shop model')
        ->and($rendered)->toContain('Payment gateway')
        ->and($rendered)->toContain('2 defined')
        // A host class is reported by base name — enough to see which gateway is
        // wired, without publishing where it lives.
        ->and($rendered)->toContain('LiveGateway');

    expect($rendered)
        // ...but never the namespace it sits in.
        ->not->toContain('App\\Billing')
        // ...nor the ledger bucket, the storage disk, the host's own field names,
        // or its tax vocabulary.
        ->not->toContain('acme-internal-ledger')
        ->not->toContain('s3-private-catalog')
        ->not->toContain('supplier_cost')
        ->not->toContain('serial_number')
        ->not->toContain('eu-oss-reduced')
        ->not->toContain('acme-wholesale');
});
