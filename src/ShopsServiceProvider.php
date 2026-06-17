<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Shops\Contracts\TaxResolver;
use RoundlyConsulting\Shops\Orders\NumberGenerators\DefaultNumberGenerator;
use RoundlyConsulting\Shops\Orders\NumberGenerators\NumberGenerator;
use RoundlyConsulting\Shops\Support\Tax\ConfigTaxResolver;

final class ShopsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/shops.php', 'shops');

        /** @var class-string<NumberGenerator> $generator */
        $generator = config('shops.orders.number_generator', DefaultNumberGenerator::class);

        $this->app->bind(NumberGenerator::class, $generator);

        /** @var class-string<TaxResolver> $taxResolver */
        $taxResolver = config('shops.tax.resolver', ConfigTaxResolver::class);

        $this->app->bind(TaxResolver::class, $taxResolver);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/shops.php' => config_path('shops.php'),
            ], 'shops-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'shops-migrations');
        }
    }
}
