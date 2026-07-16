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
 * create the package's tables — the host publishes them first. Doing both runs both
 * copies, which is a duplicate-table failure.
 */
it('never auto-loads its migrations', function (): void {
    expect(ShopsServiceProvider::class)->toNotAutoLoadMigrations();
});

it('publishes every migration timestamped under the shops-migrations tag', function (): void {
    expect(ShopsServiceProvider::class)->toPublishMigrationsTimestamped('shops-migrations', 14);
});

/**
 * Publishing preserves the dependency order the sources are numbered in, so the
 * host's migrator runs shops → products → variants → carts → orders. That ordering
 * is shops-specific (the `0001_`..`0014_` prefixes), so it stays a local pin.
 */
it('publishes the migrations in their dependency order', function (): void {
    $destinations = array_values(
        ServiceProvider::pathsToPublish(ShopsServiceProvider::class, 'shops-migrations')
    );

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

    // The capture asserts non-empty output and every `mustRender` string BEFORE it
    // looks for a secret — a negative-only check passes against empty output, which
    // is how the fleet's most credential-heavy `about` section went vacuous (#13).
    expect('shops')->toLeakNoSecrets(
        secrets: [
            // The namespace a host's gateway sits in (its base name is fine — enough
            // to see which gateway is wired, without publishing where it lives).
            'App\\Billing',
            // The ledger bucket, the storage disk, the host's own field names, and
            // its tax vocabulary.
            'acme-internal-ledger',
            's3-private-catalog',
            'supplier_cost',
            'serial_number',
            'eu-oss-reduced',
            'acme-wholesale',
        ],
        mustRender: ['Shop model', 'Payment gateway', '2 defined', 'LiveGateway'],
    );
});
