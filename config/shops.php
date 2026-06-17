<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Orders\NumberGenerators\DefaultNumberGenerator;
use RoundlyConsulting\Shops\Support\Tax\ConfigTaxResolver;

return [

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
    | 'standard' => 20 means 20%. Every product variant carries a tax class
    | (default "standard"). The legacy single "tax_rate" is folded into the
    | "standard" class.
    |
    | "resolver" is the class resolving a rate for a tax class. Bind your own
    | implementation of RoundlyConsulting\Shops\Contracts\TaxResolver to add
    | jurisdiction-aware logic; the default reads the map below.
    |
    */

    'tax_classes' => [
        'standard' => env('SHOPS_TAX_RATE', 20),
        'reduced' => 10,
        'zero' => 0,
    ],

    'tax' => [
        'resolver' => ConfigTaxResolver::class,
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
        'gateway' => env('SHOPS_PAYMENT_GATEWAY'),
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
        'method' => env('SHOPS_SHIPPING_METHOD'),
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
        'coupon_model' => env('SHOPS_COUPON_MODEL'),
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
