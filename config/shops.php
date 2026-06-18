<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Discounts\Coupon;
use RoundlyConsulting\Shops\Orders\NumberGenerators\DefaultNumberGenerator;
use RoundlyConsulting\Shops\Payments\NullPaymentGateway;
use RoundlyConsulting\Shops\Shipping\FreeShippingMethod;
use RoundlyConsulting\Shops\Shops\Shop;
use RoundlyConsulting\Shops\Support\Tax\DatabaseTaxResolver;

return [

    /*
    |--------------------------------------------------------------------------
    | Shop (Tenant) Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model that acts as the tenant every shop-owned record belongs
    | to via its "shop_id" foreign key. The package ships a fully usable Shop
    | model; point this at your own model to swap it.
    |
    */

    'shop_model' => env('SHOPS_SHOP_MODEL', Shop::class),

    /*
    |--------------------------------------------------------------------------
    | Pricing
    |--------------------------------------------------------------------------
    |
    | "price_type" controls whether catalog prices are tax-inclusive ("gross")
    | or tax-exclusive ("net"). The default is "gross", matching most EU stores;
    | net is derived by extracting the tax from the stored price.
    |
    | "default_currency" is the single currency every cart and order uses. The
    | Money value object supports any ISO 4217 code, but the package operates on
    | one configured currency at a time.
    |
    */

    'pricing' => [
        'price_type' => env('SHOPS_PRICE_TYPE', 'gross'),
        'default_currency' => env('SHOPS_DEFAULT_CURRENCY', 'EUR'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tax Classes & Resolver
    |--------------------------------------------------------------------------
    |
    | "tax_classes" maps a tax class name to its whole-number rate, e.g.
    | 'standard' => 20 means 20%. This map is the fallback floor used when a shop
    | has no matching database tax rate; the default DatabaseTaxResolver always
    | prefers per-shop rates stored in the "tax_rates" table.
    |
    | Database tax rates are stored in basis points (1900 = 19.00%), but this
    | config map stays in whole percents for authoring convenience — the config
    | fallback multiplies by 100 internally.
    |
    | "resolver" is the class resolving a rate for a (shop, class, country)
    | tuple. The default DatabaseTaxResolver reads per-shop rates and falls back
    | to this map. Bind ConfigTaxResolver to use only the config map, or your own
    | implementation of RoundlyConsulting\Shops\Contracts\TaxResolver.
    |
    */

    'tax_classes' => [
        'standard' => env('SHOPS_TAX_RATE', 20),
        'reduced' => 10,
        'zero' => 0,
    ],

    'tax' => [
        'resolver' => DatabaseTaxResolver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Inventory
    |--------------------------------------------------------------------------
    |
    | "low_stock_threshold" is the available-stock level at or below which a
    | tracked variant fires the StockRanLow event after an adjustment, so the
    | host can reorder or hide the product.
    |
    */

    'inventory' => [
        'low_stock_threshold' => env('SHOPS_LOW_STOCK_THRESHOLD', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | "fallback" is the locale used when a translatable attribute has no value
    | for the current application locale.
    |
    */

    'locales' => [
        'fallback' => env('SHOPS_FALLBACK_LOCALE', config('app.fallback_locale', 'en')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment
    |--------------------------------------------------------------------------
    |
    | "gateway" is the class charging and refunding orders. It must implement
    | RoundlyConsulting\Shops\Contracts\PaymentGateway. The package ships only a
    | null gateway that always succeeds; bind your own vendor implementation.
    |
    */

    'payment' => [
        'gateway' => env('SHOPS_PAYMENT_GATEWAY', NullPaymentGateway::class),
    ],

    /*
    |--------------------------------------------------------------------------
    | Shipping
    |--------------------------------------------------------------------------
    |
    | "method" is the class quoting shipping cost. It must implement
    | RoundlyConsulting\Shops\Contracts\ShippingMethod. The default quotes free
    | shipping; bind your own carrier implementation.
    |
    */

    'shipping' => [
        'method' => env('SHOPS_SHIPPING_METHOD', FreeShippingMethod::class),
    ],

    /*
    |--------------------------------------------------------------------------
    | Discounts
    |--------------------------------------------------------------------------
    |
    | "coupon_model" is the Eloquent model backing an order's coupon relation.
    | It must implement RoundlyConsulting\Shops\Contracts\Coupon. The package
    | ships a usable reference Coupon model; point this at your own model to
    | swap it.
    |
    */

    'discounts' => [
        'coupon_model' => env('SHOPS_COUPON_MODEL', Coupon::class),
    ],

    'orders' => [

        /*
        |----------------------------------------------------------------------
        | Order Number Generator
        |----------------------------------------------------------------------
        |
        | The class used to generate an order's human-readable number when it is
        | created. It must implement
        | RoundlyConsulting\Shops\Orders\NumberGenerators\NumberGenerator.
        |
        */

        'number_generator' => DefaultNumberGenerator::class,

        /*
        |----------------------------------------------------------------------
        | Coupon Model (deprecated alias)
        |----------------------------------------------------------------------
        |
        | Kept for backward compatibility; prefer "discounts.coupon_model".
        |
        */

        'coupon_model' => env('SHOPS_COUPON_MODEL'),

    ],

];
