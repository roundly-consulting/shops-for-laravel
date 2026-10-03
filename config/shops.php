<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Shops\Discounts\CouponPackageDiscountResolver;
use RoundlyConsulting\Shops\Orders\NumberGenerators\DefaultNumberGenerator;
use RoundlyConsulting\Shops\Payments\NullPaymentGateway;
use RoundlyConsulting\Shops\Reviews\NullVerifiedPurchaseResolver;
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
    | Key Type
    |--------------------------------------------------------------------------
    |
    | The key type used for the polymorphic customer / owner / reference columns
    | (orders, carts and stock adjustments). Use "uuid" or "ulid" when the models
    | these point at use UUID/ULID primary keys, otherwise leave it as "bigint".
    | Anything else throws an InvalidConfigurationException naming the key. It is
    | fixed when the migration first runs, so choose it before publishing the
    | migrations.
    |
    | Supported: "bigint", "uuid", "ulid"
    |
    */

    'key_type' => env('SHOPS_KEY_TYPE', 'bigint'),

    /*
    |--------------------------------------------------------------------------
    | Pricing
    |--------------------------------------------------------------------------
    |
    | "price_type" controls whether catalog prices are tax-inclusive ("gross")
    | or tax-exclusive ("net"). The default is "gross", matching most EU stores;
    | net is derived by extracting the tax from the stored price.
    |
    | "default_currency" is the ISO 4217 fallback currency: used by a shop
    | without its own "currency", by an order created without a shop, and for a
    | shop-less product's default variant. It is not the only currency: every
    | cart carries its own, and every order snapshots its currency when it is
    | created, so changing this never re-denominates a cart or a placed order.
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
    | fallback multiplies by 100 internally. A shipped class whose rate is not
    | set (left out, null, or a blank SHOPS_TAX_RATE=) takes the rate shown
    | below, never 0%; a class of your own that is not set uses "standard".
    | Set 0 explicitly for no tax. Junk such as "twenty" throws.
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
    | "low_stock_threshold" is the available-stock level a tracked variant
    | fires the StockRanLow event at: once, when an adjustment takes available
    | stock from above the threshold to at or below it, so the host can reorder
    | or hide the product.
    |
    */

    'inventory' => [
        'low_stock_threshold' => env('SHOPS_LOW_STOCK_THRESHOLD', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Catalog Media (media-library)
    |--------------------------------------------------------------------------
    |
    | Product, variant, and category imagery is stored via
    | roundly-consulting/media-library-for-laravel. Every catalog bucket (product
    | featured + gallery, variant gallery, category banner) takes these settings.
    | Catalog images are public by default (served over a CDN for SEO) with a
    | responsive width ladder used to generate srcset variants, named
    | "responsive-<width>". "disk" defaults to the media package's configured
    | disk when null; "max_file_size" is an optional per-image cap in bytes.
    |
    */

    'media' => [
        'featured_bucket' => env('SHOPS_MEDIA_FEATURED_BUCKET', 'featured'),
        'gallery_bucket' => env('SHOPS_MEDIA_GALLERY_BUCKET', 'gallery'),
        'variant_bucket' => env('SHOPS_MEDIA_VARIANT_BUCKET', 'gallery'),
        'banner_bucket' => env('SHOPS_MEDIA_BANNER_BUCKET', 'banner'),
        'disk' => env('SHOPS_MEDIA_DISK'),
        'public' => env('SHOPS_MEDIA_PUBLIC', true),
        'responsive_widths' => [320, 640, 1024, 1600],
        'max_file_size' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reviews (reviews)
    |--------------------------------------------------------------------------
    |
    | Products are reviewable via roundly-consulting/reviews-for-laravel.
    | "verified_purchase_resolver" decides whether a review by a buyer is flagged
    | as a verified purchase. The default NullVerifiedPurchaseResolver never
    | verifies; point it at DatabaseVerifiedPurchaseResolver (which checks for a
    | paid + fulfilled order containing the product, using the optional
    | Order.customer link) or your own implementation to enforce the gate.
    |
    */

    'reviews' => [
        'verified_purchase_resolver' => env(
            'SHOPS_VERIFIED_PURCHASE_RESOLVER',
            NullVerifiedPurchaseResolver::class,
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Product Attributes (attributes)
    |--------------------------------------------------------------------------
    |
    | Typed, validated spec-sheet attributes for products, backed by
    | roundly-consulting/attributes-for-laravel. These complement variant
    | *options* (which define SKUs): options are the purchasable axes, attributes
    | are the descriptive, filterable spec sheet. Each definition is keyed by
    | name with a "type" (string/integer/float/boolean/array/datetime) plus
    | optional "rules", "default", "required", and "unique".
    |
    */

    'attributes' => [
        'definitions' => [
            // 'material' => ['type' => 'string'],
            // 'weight'   => ['type' => 'integer', 'rules' => ['min:0']],
        ],
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
    | Slugs (sluggable)
    |--------------------------------------------------------------------------
    |
    | Shop, product and category slugs come from
    | roundly-consulting/sluggable-for-laravel: one slug per locale, unique
    | across shops (shops) or within a shop (products, categories), and used
    | as the route key. The indexed locales are sluggable's
    | "sluggable.locales.supported".
    |
    | "history" keeps every retired slug so an old URL answers with a 301 to
    | the current one. It needs sluggable's published slug_history migration.
    |
    */

    'slugs' => [
        'history' => env('SHOPS_SLUG_HISTORY', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment
    |--------------------------------------------------------------------------
    |
    | "gateway" is the class charging and refunding orders. It must implement
    | RoundlyConsulting\Shops\Contracts\PaymentGateway. The package ships only a
    | null gateway that always succeeds; bind your own vendor implementation.
    | ChargeOrderAction charges only $order->gatewayAmount() (the total minus
    | store credit) and skips the gateway for a zero balance. Refunds are
    | host-driven: the package never calls the gateway's refund() itself.
    |
    */

    'payment' => [
        'gateway' => env('SHOPS_PAYMENT_GATEWAY', NullPaymentGateway::class),

        /*
        |----------------------------------------------------------------------
        | Store Credit (credits)
        |----------------------------------------------------------------------
        |
        | When "allow_store_credit" is on, ChargeOrderAction applies the buyer's
        | available store credit (from roundly-consulting/credits-for-laravel)
        | before charging the gateway, allowing full or partial payment with
        | credit; a declined charge keeps none of it, and canceling an order
        | returns the credit applied to it. The buyer model must implement
        | Creditable (credits' HasCredits trait). "store_credit_bucket" is the
        | credits bucket used. "refund_to_store_credit" grants a refunded order's
        | total back to the buyer as store credit instead of a gateway refund.
        |
        */

        'allow_store_credit' => env('SHOPS_ALLOW_STORE_CREDIT', false),
        'store_credit_bucket' => env('SHOPS_STORE_CREDIT_BUCKET', 'store_credit'),
        'refund_to_store_credit' => env('SHOPS_REFUND_TO_STORE_CREDIT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Shipping
    |--------------------------------------------------------------------------
    |
    | "method" is the class quoting shipping cost. It must implement
    | RoundlyConsulting\Shops\Contracts\ShippingMethod. Checkout quotes the
    | order's shipping address through it (unless PlaceOrderData carries a
    | chosen shippingCost) and snapshots the result onto the order, where it is
    | part of the final price and the charge. The default quotes free shipping;
    | bind your own carrier implementation.
    |
    */

    'shipping' => [
        'method' => env('SHOPS_SHIPPING_METHOD', FreeShippingMethod::class),
    ],

    /*
    |--------------------------------------------------------------------------
    | Discounts (coupons)
    |--------------------------------------------------------------------------
    |
    | Coupons are powered by roundly-consulting/coupons-for-laravel. "coupon_model"
    | is the Eloquent model backing an order's coupon relation (the coupons
    | package model by default; point at your own subclass to extend it).
    | "resolver" is the DiscountResolver that turns a code + goods subtotal into a
    | discount; the shipped resolver adapts the coupons package.
    |
    */

    'discounts' => [
        'coupon_model' => env('SHOPS_COUPON_MODEL', Coupon::class),
        'resolver' => CouponPackageDiscountResolver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Addresses (addresses)
    |--------------------------------------------------------------------------
    |
    | When building an order from a customer's saved addresses (via
    | PlaceOrderData::fromAddressBook), "billing_same_as_shipping" copies the
    | primary shipping address into billing when the customer has no primary
    | billing address.
    |
    */

    'addresses' => [
        'billing_same_as_shipping' => env('SHOPS_BILLING_SAME_AS_SHIPPING', true),
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

    ],

];
