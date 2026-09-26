<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Tests;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Addresses\AddressesServiceProvider;
use RoundlyConsulting\Attributes\AttributesServiceProvider;
use RoundlyConsulting\Coupons\CouponsServiceProvider;
use RoundlyConsulting\Credits\CreditsServiceProvider;
use RoundlyConsulting\MediaLibrary\MediaLibraryServiceProvider;
use RoundlyConsulting\Money\MoneyServiceProvider;
use RoundlyConsulting\Reviews\ReviewsServiceProvider;
use RoundlyConsulting\Shops\ShopsServiceProvider;
use RoundlyConsulting\Sluggable\SluggableServiceProvider;
use RoundlyConsulting\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * Every provider shops hard-requires, in registration order. A host auto-discovers
     * these; the suite must list them or the test environment is a fiction.
     *
     * @return list<class-string<ServiceProvider>>
     */
    protected function packageProviders(): array
    {
        return [
            MoneyServiceProvider::class,
            MediaLibraryServiceProvider::class,
            AttributesServiceProvider::class,
            CouponsServiceProvider::class,
            CreditsServiceProvider::class,
            AddressesServiceProvider::class,
            ReviewsServiceProvider::class,
            SluggableServiceProvider::class,
            ShopsServiceProvider::class,
        ];
    }

    /**
     * Every provider whose migrations the suite needs, named by provider class — the
     * packages publish rather than auto-load, so the suite runs each directory itself.
     * Plus the host-owned fixture table (`customers`). Sluggable's own source is
     * its `slug_history` table, which the history-redirect tests need.
     *
     * @return list<class-string<ServiceProvider>|string>
     */
    protected function migrationSources(): array
    {
        return [
            MediaLibraryServiceProvider::class,
            AttributesServiceProvider::class,
            CouponsServiceProvider::class,
            CreditsServiceProvider::class,
            AddressesServiceProvider::class,
            ReviewsServiceProvider::class,
            SluggableServiceProvider::class,
            ShopsServiceProvider::class,
            __DIR__.'/database/migrations',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return [
            // Catalog media: store on a fakeable public disk with the GD driver and a small
            // responsive ladder so variant generation stays fast under test.
            'media.disk' => 'public',
            'media.image_driver' => 'gd',
            'media.responsive.widths' => [320, 640],

            // Coupons share the shop's single currency (one money-for-laravel Money type).
            'coupons.default_currency' => 'EUR',

            // Store credit pays orders, so its bucket is denominated in the shop currency.
            'credits.currencies' => ['store_credit' => 'EUR'],

            // A small product spec-sheet definition set, registered into the attributes
            // registry at boot, plus strict mode so unknown attributes are rejected.
            'attributes.strict' => true,
            'shops.attributes.definitions' => [
                'material' => ['type' => 'string'],
                'weight' => ['type' => 'integer', 'rules' => ['min:0']],
            ],

            // Two indexed slug locales, so the per-locale unique indexes the shops
            // migrations create are exercised beyond the app locale.
            'sluggable.locales.supported' => ['en', 'sk'],
            'sluggable.locales.fallback' => 'en',
        ];
    }
}
