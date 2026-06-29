<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Attributes\Registry\AttributeRegistry;
use RoundlyConsulting\Attributes\Registry\DefinitionFactory;
use RoundlyConsulting\Shops\Contracts\DiscountResolver;
use RoundlyConsulting\Shops\Contracts\PaymentGateway;
use RoundlyConsulting\Shops\Contracts\ShippingMethod;
use RoundlyConsulting\Shops\Contracts\TaxResolver;
use RoundlyConsulting\Shops\Discounts\CouponPackageDiscountResolver;
use RoundlyConsulting\Shops\Orders\Events\OrderRefunded;
use RoundlyConsulting\Shops\Orders\NumberGenerators\DefaultNumberGenerator;
use RoundlyConsulting\Shops\Orders\NumberGenerators\NumberGenerator;
use RoundlyConsulting\Shops\Payments\Listeners\GrantStoreCreditOnRefund;
use RoundlyConsulting\Shops\Payments\NullPaymentGateway;
use RoundlyConsulting\Shops\Reviews\Contracts\VerifiedPurchaseResolver;
use RoundlyConsulting\Shops\Reviews\NullVerifiedPurchaseResolver;
use RoundlyConsulting\Shops\Shipping\FreeShippingMethod;
use RoundlyConsulting\Shops\Shops\CurrentShop;
use RoundlyConsulting\Shops\Support\Tax\DatabaseTaxResolver;

final class ShopsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/shops.php', 'shops');

        $this->app->singleton(CurrentShop::class);

        /** @var class-string<NumberGenerator> $generator */
        $generator = config('shops.orders.number_generator', DefaultNumberGenerator::class);

        $this->app->bind(NumberGenerator::class, $generator);

        /** @var class-string<TaxResolver> $taxResolver */
        $taxResolver = config('shops.tax.resolver', DatabaseTaxResolver::class);

        $this->app->bind(TaxResolver::class, $taxResolver);

        /** @var class-string<PaymentGateway> $gateway */
        $gateway = config('shops.payment.gateway', NullPaymentGateway::class);

        $this->app->bind(PaymentGateway::class, $gateway);

        /** @var class-string<ShippingMethod> $shipping */
        $shipping = config('shops.shipping.method', FreeShippingMethod::class);

        $this->app->bind(ShippingMethod::class, $shipping);

        /** @var class-string<VerifiedPurchaseResolver> $verifiedPurchaseResolver */
        $verifiedPurchaseResolver = config(
            'shops.reviews.verified_purchase_resolver',
            NullVerifiedPurchaseResolver::class,
        );

        $this->app->bind(VerifiedPurchaseResolver::class, $verifiedPurchaseResolver);

        /** @var class-string<DiscountResolver> $discountResolver */
        $discountResolver = config('shops.discounts.resolver', CouponPackageDiscountResolver::class);

        $this->app->bind(DiscountResolver::class, $discountResolver);

        $this->app->singleton(ShopManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerProductAttributes();

        Event::listen(OrderRefunded::class, GrantStoreCreditOnRefund::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/shops.php' => config_path('shops.php'),
            ], 'shops-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'shops-migrations');
        }
    }

    /**
     * Register the product spec-sheet attribute definitions from config into the
     * attributes registry so they validate and type product attributes.
     */
    private function registerProductAttributes(): void
    {
        $definitions = config('shops.attributes.definitions', []);

        if (! is_array($definitions) || $definitions === []) {
            return;
        }

        $registry = $this->app->make(AttributeRegistry::class);

        foreach ($definitions as $name => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $registry->define(DefinitionFactory::fromArray((string) $name, $definition));
        }
    }
}
