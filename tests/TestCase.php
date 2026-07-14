<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\TestCase as Orchestra;
use ReflectionClass;
use RoundlyConsulting\Addresses\AddressesServiceProvider;
use RoundlyConsulting\Attributes\AttributesServiceProvider;
use RoundlyConsulting\Coupons\CouponsServiceProvider;
use RoundlyConsulting\Credits\CreditsServiceProvider;
use RoundlyConsulting\MediaLibrary\MediaLibraryServiceProvider;
use RoundlyConsulting\Reviews\ReviewsServiceProvider;
use RoundlyConsulting\Shops\ShopsServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            MediaLibraryServiceProvider::class,
            AttributesServiceProvider::class,
            CouponsServiceProvider::class,
            CreditsServiceProvider::class,
            AddressesServiceProvider::class,
            ReviewsServiceProvider::class,
            ShopsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // Catalog media: store on a fakeable public disk with the GD driver and a small
        // responsive ladder so variant generation stays fast under test.
        $app['config']->set('media.disk', 'public');
        $app['config']->set('media.image_driver', 'gd');
        $app['config']->set('media.responsive.widths', [320, 640]);

        // Coupons share the shop's single currency so the money bridge round-trips cleanly.
        $app['config']->set('coupons.default_currency', 'EUR');

        // A small product spec-sheet definition set, registered into the attributes
        // registry at boot, plus strict mode so unknown attributes are rejected.
        $app['config']->set('attributes.strict', true);
        $app['config']->set('shops.attributes.definitions', [
            'material' => ['type' => 'string'],
            'weight' => ['type' => 'integer', 'rules' => ['min:0']],
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadProviderSchema();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Run the migrations of the provider packages the shop integrations depend on.
     * Packages publish their migrations rather than loading them, so the suite has to
     * run each provider's directory itself.
     */
    private function loadProviderSchema(): void
    {
        $providers = [
            MediaLibraryServiceProvider::class,
            AttributesServiceProvider::class,
            CouponsServiceProvider::class,
            CreditsServiceProvider::class,
            AddressesServiceProvider::class,
            ReviewsServiceProvider::class,
        ];

        foreach ($providers as $provider) {
            $base = dirname((string) (new ReflectionClass($provider))->getFileName(), 2);

            $this->loadMigrationsFrom($base.'/database/migrations');
        }
    }
}
