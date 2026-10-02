<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops;

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Attributes\Registry\AttributeRegistry;
use RoundlyConsulting\Attributes\Registry\DefinitionFactory;
use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBlueprintMacros;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\PackageToolkit\Support\Config;
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
use RoundlyConsulting\Shops\Support\CouponModel;
use RoundlyConsulting\Shops\Support\ShopModel;
use RoundlyConsulting\Shops\Support\Tax\DatabaseTaxResolver;

final class ShopsServiceProvider extends PackageServiceProvider
{
    use RegistersBlueprintMacros;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('shops')
            ->hasConfigFile()
            ->hasMigrations()
            // A shop's config is host commerce vocabulary: the payment gateway and tax
            // resolver are the host's own classes (and reach its provider credentials),
            // the media disk is its storage topology, the store-credit bucket is its
            // ledger partition, and a product attribute name *is* a host field name.
            // Nothing here renders one — classes report by base name, destinations as
            // SET/DEFAULT, and maps as counts.
            ->contributesToAbout(static fn (): array => [
                'Shop model' => class_basename(ShopModel::class()),
                'Coupon model' => class_basename(CouponModel::class()),
                'Currency' => Currency::of((string) config('shops.pricing.default_currency', 'EUR'))->code,
                'Price type' => (string) config('shops.pricing.price_type', 'gross'),
                'Tax resolver' => self::className('shops.tax.resolver', DatabaseTaxResolver::class),
                'Tax classes' => self::size('shops.tax_classes', 'defined'),
                'Payment gateway' => self::className('shops.payment.gateway', NullPaymentGateway::class),
                'Store credit' => self::storeCredit(),
                'Shipping method' => self::className('shops.shipping.method', FreeShippingMethod::class),
                'Discount resolver' => self::className('shops.discounts.resolver', CouponPackageDiscountResolver::class),
                'Order numbers' => self::className('shops.orders.number_generator', DefaultNumberGenerator::class),
                'Low stock threshold' => (string) (int) config('shops.inventory.low_stock_threshold', 0),
                'Product attributes' => self::size('shops.attributes.definitions', 'defined'),
                'Catalog media' => self::media(),
                'Verified purchases' => self::className(
                    'shops.reviews.verified_purchase_resolver',
                    NullVerifiedPurchaseResolver::class,
                ),
                'Fallback locale' => (string) config('shops.locales.fallback', 'en'),
                'Slug history' => Config::boolean('shops.slugs.history') ? 'ON' : 'OFF',
            ]);
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(CurrentShop::class);
        $this->app->singleton(ShopsManager::class);

        $this->bindFromConfig(NumberGenerator::class, 'shops.orders.number_generator', DefaultNumberGenerator::class);
        $this->bindFromConfig(TaxResolver::class, 'shops.tax.resolver', DatabaseTaxResolver::class);
        $this->bindFromConfig(PaymentGateway::class, 'shops.payment.gateway', NullPaymentGateway::class);
        $this->bindFromConfig(ShippingMethod::class, 'shops.shipping.method', FreeShippingMethod::class);
        $this->bindFromConfig(DiscountResolver::class, 'shops.discounts.resolver', CouponPackageDiscountResolver::class);
        $this->bindFromConfig(
            VerifiedPurchaseResolver::class,
            'shops.reviews.verified_purchase_resolver',
            NullVerifiedPurchaseResolver::class,
        );
    }

    public function boot(): void
    {
        parent::boot();

        // The orders / carts / stock-adjustments migrations key their polymorphic columns
        // through the toolkit's `morphKey` macro, so it must exist before they run.
        // Registration is idempotent — the toolkit guards it with `hasMacro()`.
        $this->registerBlueprintMacros();

        $this->registerProductAttributes();

        Event::listen(OrderRefunded::class, GrantStoreCreditOnRefund::class);
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

    /**
     * A configured class by base name only. These are host classes — a payment
     * gateway or tax resolver is the host's integration with its provider, and its
     * namespace is its code, not ours.
     */
    private static function className(string $key, string $default): string
    {
        $class = config($key, $default);

        return class_basename(is_string($class) ? $class : $default);
    }

    /**
     * The size of a configured map, never its entries. A tax-class name is host
     * commerce vocabulary and a product attribute name *is* a host field name
     * (`serial_number`, `supplier_cost`).
     */
    private static function size(string $key, string $noun): string
    {
        $entries = config($key);

        if (! is_array($entries) || $entries === []) {
            return 'NONE';
        }

        return count($entries).' '.$noun;
    }

    /**
     * Store-credit tender as switches. The bucket is the host's own ledger
     * partition, so it reports presence, never its name.
     */
    private static function storeCredit(): string
    {
        if (! Config::boolean('shops.payment.allow_store_credit')) {
            return 'OFF';
        }

        $refund = Config::boolean('shops.payment.refund_to_store_credit') ? 'refunds ON' : 'refunds OFF';

        return "ON (bucket SET, {$refund})";
    }

    /**
     * Catalog media as topology presence. The disk is a host filesystem — never its
     * name — and the responsive ladder reports its size.
     */
    private static function media(): string
    {
        $disk = config('shops.media.disk');
        $widths = config('shops.media.responsive_widths');
        $visibility = Config::boolean('shops.media.public', true) ? 'public' : 'private';

        return sprintf(
            '%s, %s, %d width(s)',
            filled($disk) ? 'disk SET' : 'MEDIA DEFAULT',
            $visibility,
            is_array($widths) ? count($widths) : 0,
        );
    }
}
