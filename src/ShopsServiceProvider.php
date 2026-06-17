<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Shops\Contracts\PaymentGateway;
use RoundlyConsulting\Shops\Contracts\ShippingMethod;
use RoundlyConsulting\Shops\Contracts\TaxResolver;
use RoundlyConsulting\Shops\Orders\NumberGenerators\DefaultNumberGenerator;
use RoundlyConsulting\Shops\Orders\NumberGenerators\NumberGenerator;
use RoundlyConsulting\Shops\Payments\NullPaymentGateway;
use RoundlyConsulting\Shops\Shipping\FreeShippingMethod;
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

        /** @var class-string<PaymentGateway> $gateway */
        $gateway = config('shops.payment.gateway', NullPaymentGateway::class);

        $this->app->bind(PaymentGateway::class, $gateway);

        /** @var class-string<ShippingMethod> $shipping */
        $shipping = config('shops.shipping.method', FreeShippingMethod::class);

        $this->app->bind(ShippingMethod::class, $shipping);

        $this->app->singleton(ShopManager::class);
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
