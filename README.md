<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/shops-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=shops-for-laravel">
    <img src="art/hero.png" alt="Shops for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/shops-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/shops-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/shops-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/shops-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/shops-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/shops-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=shops-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Shops for Laravel

A production-grade e-commerce foundation for Laravel: products with variants, SKUs and
options, a race-safe inventory ledger with stock reservations, a guarded order state machine,
a persistent cart with one-call order placement, exact net/gross tax pricing on
arbitrary-precision money (orders remember their own currency), native
per-locale translations, and payment & shipping **driver contracts**. Catalog media, product
reviews, spec-sheet attributes, coupons, store credit, and customer address books are powered
by sibling roundly-consulting packages (see **Integrates with**).

## Requirements

- PHP 8.4+ with `ext-bcmath`
- Laravel 12.0 or 13.0

## Integrates with

Shops builds directly on these roundly-consulting packages (installed automatically as
dependencies):

| Package | What it powers in shops |
|---|---|
| [`money-for-laravel`](https://github.com/roundly-consulting/money-for-laravel) | Every amount: `Money`/`Currency`, exact per-line tax (`TaxRate`), discount spreading (`DiscountAllocator`), `TaxSummary`, `AsMoney`/`AsCurrency` casts and `decimal(38,0)` money columns — one Money type shared with coupons and credits |
| [`enums-for-laravel`](https://github.com/roundly-consulting/enums-for-laravel) | Order `Status`, `PriceType` and `StockReason` get `labels()`/`options()`/`validationRule()` and other helpers |
| [`media-library-for-laravel`](https://github.com/roundly-consulting/media-library-for-laravel) | Product featured/gallery images, per-variant images, category banners (public, responsive) |
| [`reviews-for-laravel`](https://github.com/roundly-consulting/reviews-for-laravel) | Product reviews, rating aggregates, verified-purchase gating |
| [`attributes-for-laravel`](https://github.com/roundly-consulting/attributes-for-laravel) | Typed, filterable product spec-sheet attributes |
| [`coupons-for-laravel`](https://github.com/roundly-consulting/coupons-for-laravel) | All coupon/discount logic (percentage in basis points, fixed, free shipping, caps, currency lock, usage limits) |
| [`credits-for-laravel`](https://github.com/roundly-consulting/credits-for-laravel) | "Pay with store credit" tender and store-credit refunds, on a currency-denominated bucket |
| [`addresses-for-laravel`](https://github.com/roundly-consulting/addresses-for-laravel) | Customer address book → order billing/shipping snapshot |
| [`sluggable-for-laravel`](https://github.com/roundly-consulting/sluggable-for-laravel) | Per-locale shop/product/category slugs — unique per shop, DB-enforced, locale-aware route binding, optional SEO slug history |

All coupon and discount logic lives in `coupons-for-laravel`.

## Installation

Install the package via Composer:

```bash
composer require roundly-consulting/shops-for-laravel
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="shops-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="shops-config"
```

Migrations are **publish-only** — the package never loads them itself, so publishing is a
required step, not an optional one. The fourteen files publish in dependency order (shops →
products → variants → carts → orders), so a single `php artisan migrate` applies them cleanly.
The six provider packages shops builds on (media-library, reviews, attributes, coupons, credits,
addresses) publish their own migrations the same way — publish each before migrating.
Sluggable's only migration (`sluggable-migrations`, the `slug_history` table) is needed just when
you turn on `shops.slugs.history`.

The shops, products and categories migrations create one unique slug index **per supported
locale** — set `sluggable.locales.supported` (e.g. `['en', 'sk']`) before migrating; it defaults
to your `app.locale` + `app.fallback_locale`. See [Slugs & URLs](#slugs--urls).

## Configuration

The published `config/shops.php` documents every key. The most relevant ones:

```php
return [
    // The tenant model every shop-owned record belongs to via "shop_id".
    'shop_model' => env('SHOPS_SHOP_MODEL', \RoundlyConsulting\Shops\Shops\Shop::class),

    'pricing' => [
        'price_type' => env('SHOPS_PRICE_TYPE', 'gross'),
        'default_currency' => env('SHOPS_DEFAULT_CURRENCY', 'EUR'),
    ],

    // Fallback floor (whole percents) used when a shop has no database rate.
    'tax_classes' => [
        'standard' => env('SHOPS_TAX_RATE', 20),
        'reduced' => 10,
        'zero' => 0,
    ],

    'tax' => [
        'resolver' => \RoundlyConsulting\Shops\Support\Tax\DatabaseTaxResolver::class,
    ],

    'orders' => [
        'number_generator' => DefaultNumberGenerator::class,
    ],

    'discounts' => [
        'coupon_model' => env('SHOPS_COUPON_MODEL', \RoundlyConsulting\Coupons\Models\Coupon::class),
        'resolver' => \RoundlyConsulting\Shops\Discounts\CouponPackageDiscountResolver::class,
    ],
];
```

| Key | Type | Default | Env | Description |
|---|---|---|---|---|
| `shop_model` | `class-string` | `Shops\Shop::class` | `SHOPS_SHOP_MODEL` | The Eloquent tenant model every owned record points at via its `shop_id` foreign key. Swap in your own model to extend it. |
| `key_type` | `string` | `bigint` | `SHOPS_KEY_TYPE` | Key type of the polymorphic `customer` / `owner` / `reference` columns (orders, carts, stock adjustments): `bigint`, `uuid` or `ulid` — match your customer/owner models' keys. Anything else throws an `InvalidConfigurationException`. Fixed when the migrations first run. |
| `pricing.price_type` | `string` | `gross` | `SHOPS_PRICE_TYPE` | `gross` (tax is extracted from the stored price) or `net` (tax is added on top). Anything else throws an `InvalidConfigurationException` listing both. |
| `pricing.default_currency` | `string` | `EUR` | `SHOPS_DEFAULT_CURRENCY` | ISO-4217 fallback currency: for a shop without its own `currency`, an order without a shop, and a shop-less product's default variant. Carts carry their own currency and orders snapshot theirs. |
| `tax_classes` | `array<string,int>` | `standard 20, reduced 10, zero 0` | `SHOPS_TAX_RATE` (standard) | Whole-percent fallback floor used when a shop has no matching database tax rate. Each rate is `0`–`100` (an `int` or a string such as `"21"`); `"twenty"` or `"19.5"` throws instead of becoming 0 %. A blank rate (`SHOPS_TAX_RATE=`) is not set and reads as `0` %, like a `null` one. |
| `tax.resolver` | `class-string` | `DatabaseTaxResolver::class` | — | Resolves the rate for a `(shop, class, country)` lookup. Defaults to per-shop database rates with the config map as the floor. Bind `ConfigTaxResolver` to use only the config map, or your own `TaxResolver`. |
| `inventory.low_stock_threshold` | `int` | `0` | `SHOPS_LOW_STOCK_THRESHOLD` | `StockRanLow` fires once when an adjustment takes a tracked variant's available stock from above this level to at or below it. At least `0`; a non-integer value throws. |
| `media.featured_bucket` | `string` | `featured` | `SHOPS_MEDIA_FEATURED_BUCKET` | Product featured-image bucket (single file). |
| `media.gallery_bucket` | `string` | `gallery` | `SHOPS_MEDIA_GALLERY_BUCKET` | Product gallery bucket. |
| `media.variant_bucket` | `string` | `gallery` | `SHOPS_MEDIA_VARIANT_BUCKET` | Variant gallery bucket. |
| `media.banner_bucket` | `string` | `banner` | `SHOPS_MEDIA_BANNER_BUCKET` | Category banner bucket (single file). |
| `media.disk` | `?string` | `null` (media's disk) | `SHOPS_MEDIA_DISK` | Disk for every catalog bucket. |
| `media.public` | `bool` | `true` | `SHOPS_MEDIA_PUBLIC` | Public (CDN/SEO) or private catalog media. |
| `media.responsive_widths` | `list<int>` | `[320, 640, 1024, 1600]` | — | Responsive ladder; each width is generated as the variant `responsive-<width>`. Every width must be a positive integer — a bad entry throws rather than being dropped. |
| `media.max_file_size` | `?int` | `null` | — | Per-image cap in bytes, applied to every catalog bucket. `null` for none; otherwise at least `1`. |
| `reviews.verified_purchase_resolver` | `class-string` | `NullVerifiedPurchaseResolver::class` | `SHOPS_VERIFIED_PURCHASE_RESOLVER` | Decides whether a buyer's review is a verified purchase (see [Reviews](#reviews)). |
| `attributes.definitions` | `array<string,array>` | `[]` | — | Product spec-sheet attribute definitions (see [Spec sheet](#spec-sheet-attributes)). |
| `locales.fallback` | `string` | `app.fallback_locale` | `SHOPS_FALLBACK_LOCALE` | Locale a translatable attribute falls back to; also the locale the default variant SKU derives from. |
| `slugs.history` | `bool` | `false` | `SHOPS_SLUG_HISTORY` | Keep retired shop/product/category slugs and answer old URLs with a 301 to the current one. Needs sluggable's published `slug_history` migration. |
| `payment.gateway` | `class-string` | `NullPaymentGateway::class` | `SHOPS_PAYMENT_GATEWAY` | Your `PaymentGateway`. The default always succeeds without taking money. |
| `payment.allow_store_credit` | `bool` | `false` | `SHOPS_ALLOW_STORE_CREDIT` | Apply the buyer's store credit before charging the gateway (see [Pay with store credit](#pay-with-store-credit)). |
| `payment.store_credit_bucket` | `string` | `store_credit` | `SHOPS_STORE_CREDIT_BUCKET` | The credits bucket store credit is taken from and returned to. |
| `payment.refund_to_store_credit` | `bool` | `false` | `SHOPS_REFUND_TO_STORE_CREDIT` | Grant a refunded order's total back as store credit. |
| `shipping.method` | `class-string` | `FreeShippingMethod::class` | `SHOPS_SHIPPING_METHOD` | Your `ShippingMethod`; checkout quotes the shipping address through it. |
| `discounts.coupon_model` | `class-string` | coupons' `Coupon::class` | `SHOPS_COUPON_MODEL` | The Eloquent model backing an order's coupon relation: coupons-for-laravel's `Coupon` or your subclass of it (anything else throws an `InvalidConfigurationException` naming the key). |
| `discounts.resolver` | `class-string` | `CouponPackageDiscountResolver::class` | — | The `DiscountResolver` pricing carts and snapshotting an order's discount at place-order. |
| `addresses.billing_same_as_shipping` | `bool` | `true` | `SHOPS_BILLING_SAME_AS_SHIPPING` | Reuse the primary shipping address as billing when the customer has no billing address. |
| `orders.number_generator` | `class-string` | `DefaultNumberGenerator::class` | — | The class used to generate an order number. Must implement `NumberGenerator`. |

The `bool` switches accept `true`/`false`, `1`/`0`, `on`/`off` and `yes`/`no`, so any env
spelling works. Anything else throws an `InvalidConfigurationException` naming the key, so a
typo never quietly becomes the default.

The other settings are just as strict. A key that is not set — left out, `null` or blank (`''` or
whitespace, such as a `SHOPS_DEFAULT_CURRENCY=` line) — takes its default; a value of the wrong
shape throws an `InvalidConfigurationException` naming the key: integers take an `int` or a
whole-number string such as `"5"` (never `"five"` or `"5.5"`), names (currency, buckets, disk,
locale) must be strings, and the maps and lists must be arrays (each responsive width a real
width — a blank entry inside the list throws). A tax class whose rate is not set reads as `0`
%, so a blank `SHOPS_TAX_RATE=` charges no tax on the `standard` class. `php artisan about` shows
a broken setting as `INVALID`.

## Usage

### The `Shops` facade

`Shops` is the whole API in one place. Each area is a scoped handle or a sub-accessor:

```php
use RoundlyConsulting\Shops\Facades\Shops;
use RoundlyConsulting\Shops\Inventory\Enums\StockReason;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\PlaceOrderData;
use RoundlyConsulting\Shops\Orders\Enums\Status;

// Cart — lines of another cart are refused (ForeignItemException)
Shops::cart($cart)->add($variant, 2);            // CartItem; the same variant merges into its line
Shops::cart($cart)->update($item, 3);            // ?CartItem — 0 removes the line
Shops::cart($cart)->remove($item);
Shops::cart($cart)->clear();                     // the "empty cart" button
Shops::cart($cart)->price('SUMMER');             // Price DTO, optionally with a coupon code
Shops::cart($cart)->subtotal();                  // Money
$order = Shops::cart($cart)->checkout(new PlaceOrderData(couponCode: 'SUMMER'));

// Order
Shops::order($order)->transition(Status::InProgress);
Shops::order($order)->charge();                  // PaymentResult — Paid on success; New/InProgress only
Shops::order($order)->fulfil();                  // reservation becomes a sale
Shops::order($order)->cancel();                  // reservation released, store credit returned
Shops::order($order)->refund();                  // an unfulfilled order's reservation released too
Shops::order($order)->quoteShipping($address);   // Money, through the bound ShippingMethod

// Inventory — every change is a StockAdjustment ledger row
Shops::inventory($variant)->receive(10, ref: $purchaseOrder, note: 'PO-17');
Shops::inventory($variant)->returned(1, $order); // the variant must be on that order
Shops::inventory($variant)->adjust(-2, StockReason::Manual, note: 'Damaged');
Shops::inventory($variant)->available();         // stock - reserved

// Coupons, tenancy, addresses
Shops::coupons()->preview('WELCOME10', Money::ofMinor(1000, 'EUR')); // DiscountResult, no redemption
Shops::current()->set($shop);                    // CurrentShop: set / get / id / forget / run
Shops::addresses()->defaults($customer);         // OrderAddresses (billing, shipping)
```

The model convenience methods — `$cart->add()`, `$order->transitionTo()`, `markInProgress()`,
`markPaid()`, `markFulfilled()`, `cancel()`, `refund()`, `Shop::current()` — go through the same
manager, so they behave identically and `Shops::fake()` sees them.

The facade is also registered as the `Shops` alias.

#### Without the facade

The facade's root is `ShopsManager`, a singleton you can inject; every call resolves its action
from the container, so your bindings apply. The actions are public too, for queued jobs and your
own actions:

```php
use RoundlyConsulting\Shops\Actions\Cart\AddToCartAction;
use RoundlyConsulting\Shops\Actions\Inventory\AdjustStockAction;
use RoundlyConsulting\Shops\Actions\Orders\PlaceOrderAction;
use RoundlyConsulting\Shops\ShopsManager;

final class CheckoutController
{
    public function __construct(private ShopsManager $shops) {}

    public function __invoke(Request $request, Cart $cart): Order
    {
        return $this->shops->cart($cart)->checkout(PlaceOrderData::fromAddressBook($request->user()));
    }
}

app(AddToCartAction::class)->execute($cart, $variant, 2);
app(PlaceOrderAction::class)->execute($cart, new PlaceOrderData);
app(AdjustStockAction::class)->execute($variant, 10, StockReason::Received, $purchaseOrder, 'PO-17');
```

| Facade | Action |
|---|---|
| `cart()->add()` | `Actions\Cart\AddToCartAction` |
| `cart()->update()` | `Actions\Cart\UpdateCartItemAction` |
| `cart()->remove()` | `Actions\Cart\RemoveFromCartAction` |
| `cart()->clear()` | `Actions\Cart\ClearCartAction` |
| `cart()->checkout()` | `Actions\Orders\PlaceOrderAction` |
| `order()->transition()` / `cancel()` / `refund()` / `fulfil()` | `Actions\Orders\TransitionOrderStatusAction` |
| `order()->charge()` | `Actions\Orders\ChargeOrderAction` |
| `order()->quoteShipping()` | `Actions\Orders\QuoteShippingAction` |
| `inventory()->receive()` / `returned()` / `adjust()` | `Actions\Inventory\AdjustStockAction` |

Checkout's building blocks (`AddOrderItemAction`, `ReserveStockAction`, `ReleaseStockAction`)
are `@internal`.

#### Faking it in your tests

`Shops::fake()` swaps in `ShopsFake`, a recording subtype of `ShopsManager` (so injected managers
get it too). It is a spy: every operation still runs — carts, orders and stock rows are real and
events fire — and each state change is recorded, whether it came through the facade, an injected
manager or a model method. A charge still goes through the bound `PaymentGateway`; the default
`NullPaymentGateway` charges nothing.

```php
use RoundlyConsulting\Shops\Testing\CartChange;

Shops::fake();

$this->post('/checkout')->assertRedirect();

Shops::assertCartChanged($cart, CartChange::Added);   // Added / Updated / Removed / Cleared
Shops::assertOrderPlaced($cart);
Shops::assertCharged($order);
Shops::assertTransitioned($order, Status::Paid);
Shops::assertStockAdjusted($variant, delta: 10, reason: StockReason::Received);

// Each has a negative: assertNothingPlaced(), assertNothingCharged(), assertNothingTransitioned(),
// assertNothingStockAdjusted(), assertNothingCartChanged().
```

Only the calls your code makes are recorded: the stock a checkout reserves is part of
`assertOrderPlaced`, and the transitions a successful charge makes are part of `assertCharged`.

### Query scopes & route binding

```php
Product::query()->published();          // published_at set and not in the future
Product::query()->unpublished();
Product::query()->forShop($shop);       // filter by a Shop model or a raw shop id
ProductVariant::query()->inStock(2);    // variants with >= 2 available (or untracked)
```

Orders are route-bound by their `number`; products, categories and shops by their per-locale
`slug` (see [Slugs & URLs](#slugs--urls)).

### Shops (tenancy)

A `Shop` is the concrete tenant every owned record belongs to through a plain `shop_id`
foreign key. A single-shop app can ignore it entirely (the column is nullable); a multi-shop
app creates shops and scopes data to them.

```php
use RoundlyConsulting\Shops\Facades\Shops;
use RoundlyConsulting\Shops\Shops\Shop;

$shop = Shop::create(['name' => 'Acme EU', 'currency' => 'EUR']);
$shop->currency();   // Currency (EUR) — the per-shop override, or the configured default when null

// Explicit ownership always wins:
$product = Product::create(['shop_id' => $shop->id, 'name' => 'Sparkling Water']);

// Or bind a current shop and let ownership auto-fill on create:
Shops::current()->set($shop);                        // a Shop or its id
Product::create(['name' => 'Still Water']);          // shop_id auto-filled
Shops::current()->id();                              // ?int
Shops::current()->forget();

// Scoped block — restores the previous binding afterwards (even on exception):
Shops::current()->run($shop, function () {
    Product::create(['name' => 'Tonic']);            // belongs to $shop
});

Shops::current()->get();                 // ?Shop bound to the current context
Shop::current();                         // the same, typed to the package's Shop model

Product::query()->forShop($shop)->get();       // scope by model
Product::query()->forShop($shop->id)->get();   // or by id
```

The current shop is bound **per request and per queued job** (a scoped binding): Octane drops it
between requests and the queue worker before each job, so one tenant never leaks into the next —
set it in a middleware or at the top of a job. Swap the tenant model by pointing
`shops.shop_model` at your own class.

### Per-shop tax rates

Each shop owns many `TaxRate` rows. A rate carries a tax class, an optional ISO-3166-1
alpha-2 country, and a rate in **integer basis points** (1 bp = 0.01%, so `1900` = 19.00%,
`850` = 8.5%). The default `DatabaseTaxResolver` resolves a `(shop, class, country)` lookup
through a fallback chain: an exact country match, then the shop's class default (the rate
flagged `is_default` — a country-less one first, else a country-specific one such as your home
country's), then the highest-priority class rate, then the `tax_classes` config floor, then
zero. Countries match case-insensitively (`de` = `DE`), and a rate's country is stored
upper-cased.

```php
use RoundlyConsulting\Shops\Shops\TaxRate;
use RoundlyConsulting\Shops\Contracts\TaxResolver;

$shop->taxRates()->create([
    'name' => 'Germany Standard', 'tax_class' => 'standard',
    'country' => 'DE', 'rate' => 1900, 'is_default' => true,   // 19.00%
]);
$shop->taxRates()->create([
    'name' => 'Luxembourg Reduced', 'tax_class' => 'reduced',
    'country' => 'LU', 'rate' => 850,                          // 8.50%
]);

$value = app(TaxResolver::class)->rateFor($shop, 'reduced', 'LU');
$value->basisPoints;                 // 850
$value->percentage()->value();       // "8.5" — money's Percentage
$value->toTaxRate();                 // money's TaxRate, which does the exact tax math
$value->isZero();                    // false

app(TaxResolver::class)->rateFor($shop, 'standard');   // 1900 — DE, the flagged default
app(TaxResolver::class)->rateFor($shop, 'standard', 'it'); // 1900 — no IT rate: the default
app(TaxResolver::class)->rateFor(null, 'standard');    // config floor (2000 bp = 20%)
```

Order and cart pricing automatically use the owning shop's rate (and, when an order has a
shipping address, its country), so tax differs correctly per shop.

### Products and categories

Products and categories belong to an optional `shop` tenant (so the same tables serve a
single shop or many), generate a URL slug from their `name`, and use the slug as the route
key.

```php
use RoundlyConsulting\Shops\Products\Product;
use RoundlyConsulting\Shops\Products\Category;
use RoundlyConsulting\Money\Money;

$category = Category::create(['name' => 'Beverages']);
// $category->slug === 'beverages'

$product = Product::create(['name' => 'Sparkling Water']);
$product->categories()->attach($category);

$product->price; // RoundlyConsulting\Money\Money — proxied from the default variant
```

`name`, `slug`, and `description` on products and categories are **translatable**, stored as
native per-locale JSON. Reads transparently return the current locale's value (falling back to
`shops.locales.fallback`); a plain-string write is stored under the current locale, so simple
single-language stores need no extra work:

```php
$product->setTranslation('name', 'en', 'Sparkling Water');
$product->setTranslation('name', 'sk', 'Perlivá voda');
$product->save();

app()->setLocale('sk');
$product->name;                       // "Perlivá voda"
$product->getTranslation('name', 'en'); // "Sparkling Water"
$product->getTranslations('name');      // ['en' => '...', 'sk' => '...']
```

Slugs are generated per locale from the name's translations.

### Slugs & URLs

Shop, product and category slugs are powered by
[`sluggable-for-laravel`](https://github.com/roundly-consulting/sluggable-for-laravel). Each model
stores one slug per locale in its `slug` column and uses it as the route key:

| Model | Unique | Example |
|---|---|---|
| `Shop` | across all shops, per locale | `acme`, `acme-2` |
| `Product` | within its shop, per locale | two shops may both sell `chair`; a second "Chair" in one shop is `chair-2` |
| `Category` | within its shop, per locale | same as products |

- **Generated per locale** from `name`: `['en' => 'Chair', 'sk' => 'Stolička']` →
  `['en' => 'chair', 'sk' => 'stolicka']`.
- **Enforced by the database** — the migrations add a unique index per supported locale (scoped
  by `shop_id` on products and categories), so even a race or a raw insert cannot duplicate one.
  Soft-deleted rows keep their slug reserved, so a restore never collides.
- **Manual slugs** are normalised and made unique (`'Custom Slug!'` → `custom-slug`); a locale
  added later is filled in on the next save; an empty name gets a random slug.
- **Route binding** matches the request locale, then `shops.locales.fallback`, then any locale —
  and works on every database engine, including tenant-scoped routes:

```php
Route::get('/shops/{shop}/products/{product}', ShowProduct::class)->scopeBindings();
// /shops/acme/products/chair — another shop's "chair" is a 404
```

Every sluggable reader and scope is available on the three models:

```php
$product->currentSlug();              // "stolicka" under sk
$product->slugFor('en');              // "chair"
$product->slugMap();                  // ['en' => 'chair', 'sk' => 'stolicka']
Product::query()->forShop($shop)->whereSlug('chair')->first();
```

The default variant's SKU derives from the **fallback-locale** slug (`CHAIR-DEFAULT`), so it is
the same whatever locale the creating request ran under.

**Slug history (opt-in).** Set `SHOPS_SLUG_HISTORY=true` and publish sluggable's migration
(`php artisan vendor:publish --tag="sluggable-migrations"`) — a product, category or shop whose
**slug** changes keeps answering its old URL with a 301 to the new one. Renaming does not change
the slug: a slug is generated once, from the first name, so links stay stable. To move the URL
with a rename, set the new slug yourself (`$product->setTranslation('slug', 'en',
'armchair')->save()`) — with history on, the old one redirects.

**Importing existing catalogue data.** Rows imported with duplicate slugs must be fixed before
the unique slug indexes can be added. Per model (`Shops\Shop`, `Products\Product`,
`Products\Category`): find duplicates, preview and apply the rewrite, then add the indexes:

```bash
php artisan sluggable:duplicates "RoundlyConsulting\Shops\Products\Product"
php artisan sluggable:regenerate "RoundlyConsulting\Shops\Products\Product" --mode=all --dry-run
php artisan sluggable:regenerate "RoundlyConsulting\Shops\Products\Product" --mode=all
php artisan sluggable:indexes "RoundlyConsulting\Shops\Products\Product"
```

Adding a locale later works the same way: `sluggable:regenerate … --locale=de` backfills it and
`sluggable:indexes` adds its index.

### Variants, SKUs and options

Every sellable unit is a `ProductVariant` with its own `sku`, `price`, `currency`, tax class,
and stock. Every product gets **one default variant** when it is created (`<SLUG>-DEFAULT`, a
zero price in its shop's currency, no stock) — it is a real, sellable variant, not a
placeholder that later variants replace. `$product->defaultVariant` and `$product->price` read
the lowest-position variant, which is that default unless you reorder. So set it up rather than
leaving it at zero: price it for a simple product, or make it your first option variant and add
the others next to it:

```php
use RoundlyConsulting\Money\Money;

// The price cast writes the `currency` column from the Money itself.
$small = $product->defaultVariant;
$small->update(['sku' => 'WATER-0.5L', 'price' => Money::ofMinor(199, 'EUR'), 'stock' => 50]);

$large = $product->variants()->create([
    'sku' => 'WATER-1L', 'price' => Money::ofMinor(299, 'EUR'), 'stock' => 30, 'position' => 1,
]);

$product->price;                // 1.99 EUR — the default (lowest-position) variant

// Re-pricing in another currency: set `currency` BEFORE `price` — the cast refuses to
// silently re-denominate a column that already holds another code (CurrencyMismatch).
$small->update(['currency' => 'USD', 'price' => Money::ofMinor(219, 'USD')]);

$product->defaultVariant;       // lowest-position variant (WATER-0.5L)
$small->inStock(10);            // bool — respects track_stock and reserved quantity
$small->availableStock();       // stock - reserved
```

Options (e.g. Size, Colour) compose variants. Resolve a variant from a set of option values:

```php
use RoundlyConsulting\Shops\Products\ProductOption;

$size = ProductOption::create(['product_id' => $product->id, 'name' => 'Size']);
$small = $size->values()->create(['value' => 'S']);
$large = $size->values()->create(['value' => 'L']);

$variant->optionValues()->attach([$small->id]);

$product->variantFor([$small->id]); // ?ProductVariant
```

### Inventory & stock

Stock is an auditable ledger: every change is a `StockAdjustment` row, and the variant caches
`stock` (on hand) and `reserved` (held for pending orders). Every write goes through
`AdjustStockAction`, which row-locks the variant inside a transaction and decides a sale or a
reservation against that locked row — never a stale copy — so two checkouts can never both take
the last unit. The variant you pass in gets the new `stock`/`reserved` as clean attributes (a
later `save()` of it never rewrites stock another request has moved).
`Shops::inventory($variant)` is the way in:

```php
use RoundlyConsulting\Shops\Facades\Shops;
use RoundlyConsulting\Shops\Inventory\Enums\StockReason;

Shops::inventory($variant)->receive(100, ref: $purchaseOrder, note: 'PO-17'); // +100 on hand
Shops::inventory($variant)->returned(1, $order);                             // +1, booked against the order
Shops::inventory($variant)->adjust(-2, note: 'Stock count');                  // Manual correction, either sign
Shops::inventory($variant)->adjust(-1, StockReason::Sold, $posSale);          // sell one outside an order
Shops::inventory($variant)->available();                                     // stock - reserved
Shops::inventory($variant)->inStock(3);                                      // bool
```

Each call returns the `StockAdjustment` row; `ref` is any model that explains the change (a
purchase order, an RMA, the order). A return against an `Order` the variant was never on throws
`ForeignItemException`.

The sign must match the reason — `Received`, `Returned` and `Reserved` add (positive),
`Sold` and `Released` remove (negative), `Manual` goes either way — and a zero delta is
refused; both throw `InvalidQuantityException` before anything is written.

Selling or reserving more than the available stock of a `track_stock` variant throws
`InsufficientStockException`; variants with `track_stock = false` (digital/unlimited goods)
never throw. Every adjustment fires `StockAdjusted`; `StockRanLow` fires once when an
adjustment takes available stock from above `shops.inventory.low_stock_threshold` to at or
below it (not again while it stays low). Both fire after the surrounding transaction commits —
a rolled-back checkout fires nothing.

Orders manage reservations automatically: checkout holds each line's quantity when
an order is placed, canceling an order — or refunding one that was paid but never fulfilled —
**releases** the hold, and fulfilling an order **converts** the reservation into a sale
(decrementing on-hand stock). Refunding a fulfilled order leaves stock alone; book the return
with `Shops::inventory($variant)->returned($qty, $order)`. An oversell during reservation rolls
back the whole order and holds nothing.

### Money

Every amount in shops is a [money-for-laravel](https://github.com/roundly-consulting/money-for-laravel)
`RoundlyConsulting\Money\Money`: minor units as an exact integer string in a registered
currency with its real exponent (JPY 0, EUR 2, BHD 3). Prices are `decimal(38,0)` columns
cast with `AsMoney::currencyColumn('currency')`; carts and orders cast `currency` to a
`Currency`. Shops ships no money primitive of its own — coupons and credits speak the same
type, so nothing is converted between packages.

```php
use RoundlyConsulting\Money\Money;

$price = Money::ofMinor(1000, 'EUR');       // 10.00 EUR
$total = Money::sum([$price, Money::ofMinor(500, 'EUR')]); // 15.00 EUR
$price->minor();                            // "1000" (string — exact past int64)
$price->currency()->code;                   // "EUR"
(string) $price;                            // "10.00 EUR"
```

Mixing currencies throws money's `CurrencyMismatch` — including adding a variant to a cart or
order in another currency.

### Orders and pricing

### Cart & placing an order

A `Cart` is a persistent basket with an optional polymorphic `owner` (a user) or a guest
`token` for anonymous checkout, plus its currency. Add variants to it (a variant priced in
another currency throws `CurrencyMismatch`) and read its price through the same engine orders
use:

```php
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Facades\Shops;

$cart = Cart::create(['currency' => 'EUR']);
$item = Shops::cart($cart)->add($variant, 2); // snapshots name/sku/price; same variant increments
$cart->add($variant);                         // the same, as a model method

Shops::cart($cart)->subtotal();  // Money
Shops::cart($cart)->price();     // Price DTO (pass a coupon code to discount it)

Shops::cart($cart)->update($item, 3); // set a line's quantity
Shops::cart($cart)->update($item, 0); // 0 removes the line (returns null)
Shops::cart($cart)->remove($item);
Shops::cart($cart)->clear();          // every line; the cart itself stays
```

The cart row is locked while a line is added, so a double-clicked "add" lands on one line. A
line of another cart is refused with `RoundlyConsulting\Shops\Exceptions\ForeignItemException`
before anything is written — scope carts to the current customer and let the handle guard the
line ids that arrive in a request.

A quantity is a whole number of items from 1 to 32 767 (`Support\Quantity::MAX`, the range of
the quantity columns). Anything else — zero or negative in `add()`, negative in `update()`, a
fraction, or a line that would grow past
the maximum — throws `RoundlyConsulting\Shops\Exceptions\InvalidQuantityException` before
anything is written. `CartItem` and order `Item` guard their `quantity` on every write too
(integer strings such as request input are accepted), and a `PriceLine` needs at least one item.

`Shops::cart($cart)->checkout()` turns a cart into an order in one transaction — copying each
line at the name, sku, price and tax class **the cart snapshotted** (what the customer saw, even
if the catalog was repriced since), reserving stock, linking a coupon by code, quoting and
snapshotting shipping, storing the billing/shipping address, generating the number, firing
`OrderPlaced`, and clearing the cart. An oversell rolls everything back and leaves the cart
untouched.

The cart row stays locked for the whole checkout, so a double-submitted "Place order" places
**one** order: the second request waits, finds the cart emptied and is refused with
`Orders\Exceptions\CheckoutRefusedException` (catch it and show the order the first request
placed). The same exception — thrown before anything is written — refuses an empty cart and a
cart with a line whose variant was deleted or soft-deleted since it was added
(`$e->cartItem` names the line), instead of silently dropping it:

```php
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Address;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\PlaceOrderData;

$order = Shops::cart($cart)->checkout(new PlaceOrderData(
    billing: new Address('Ada Lovelace', '1 Analytical Way', 'London', 'EC1', 'GB'),
    couponCode: 'WELCOME10',
));

$order = Shops::cart($cart)->checkout(); // no addresses, the cart's own coupon_code
```

Billing and shipping addresses are stored as JSON and cast to an immutable `Address` DTO.

**Shipping.** With a `shipping` address, checkout asks the bound `ShippingMethod`
(`shops.shipping.method`) to quote it — after the lines are added, so the method can price them
— and snapshots the result onto the order (`shipping_cost`, in the order currency). Pass the
option the customer chose instead with `new PlaceOrderData(shipping: $address, shippingCost:
Money::ofMinor(490, 'EUR'))`. The order's final price and the charge include it, and a
free-shipping coupon takes it off. No address and no chosen cost: no shipping charge. A cart has
no destination, so `Shops::cart($cart)->price()` never includes shipping.

### Orders and pricing

An order has many `items`, an optional `coupon`, an automatically generated `number`, and its
own **`currency`** — snapshotted when it is created (from the cart at place-order, else its
shop's, else `SHOPS_DEFAULT_CURRENCY`), so changing the configured default never
re-denominates historical orders. Its `price` accessor returns a `Price` DTO that computes discount, shipping, tax, and the final
total from the order's items, its snapshotted shipping (`shipping_cost`) and the discount
**snapshotted when the order was placed** (`discount`, `free_shipping`, `coupon_code`) — a coupon that later expires, is revoked or runs
out of uses never re-prices a placed order. The same holds for tax: checkout
snapshots the rate each line's tax class resolves to (`order_items.tax_rate` in basis points +
`tax_label`) and the order keeps the `price_type` it was created under, so editing a shop's tax
rates, changing the shipping address or flipping `shops.pricing.price_type` later never changes
what a placed order costs. An item written without a snapshot (`tax_rate` null) is taxed at the
live rate.

```php
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Orders\Item;
use RoundlyConsulting\Money\Money;

$order = Order::create([]);              // number auto-generated, e.g. "24000001"

Item::create([
    'order_id' => $order->id,
    'name' => 'Sparkling Water',
    'quantity' => 2,
    'price' => Money::ofMinor(199, 'EUR'), // must match the order's currency
]);

$price = $order->price;                  // RoundlyConsulting\Shops\Orders\DataTransferObjects\Price

$price->getSubtotal();           // sum of line totals (quantity-aware), before discount
$price->getPriceAfterDiscount(); // after the placed discount (if any)
$price->getNetPrice();           // tax-exclusive value of the goods
$price->getTaxPrice();           // tax across the goods (after discount)
$price->getDiscountValue();      // amount saved by the placed discount
$price->shippingCost();          // the shipping charged: shipping_cost, or zero with free shipping
$price->getFinalPrice();         // amount the customer pays (goods + shipping, tax-correct)
$price->taxSummary();            // money TaxSummary: net / tax / gross per rate (invoice VAT table)
```

`$order->price` is computed once per model instance and cached, together with its loaded
`items`: after changing an order in memory (adding items, applying a discount) call
`$order->refresh()` before reading the price again — and before handing it to
`StoreCreditTender`, which debits the price it reads. `Shops::order($order)->charge()` re-reads
the order as stored, under its row lock, before charging. `checkout()` already hands back a
refreshed order.

Pricing is **quantity-aware** (a 2× line is billed twice) and **tax-correct** for both
tax-inclusive and tax-exclusive catalogs. Set `shops.pricing.price_type` to `gross`
(default — tax is *extracted* from the price) or `net` (tax is *added on top*). Tax rates
are resolved per line by the owning shop's database rates (with country awareness), falling
back to the `tax_classes` config map; host applications can supply their own jurisdiction
logic by binding a custom `TaxResolver`.

Tax is **exact**: an order-level discount is first spread over the lines in proportion to
their totals (money's `DiscountAllocator` — the shares sum to the discount), then each line is
taxed once on its discounted amount with money's `TaxRate` (gross: `gross × 100 / (100 +
rate)`; net: `net × rate`), rounding half away from zero. No floats, so a gross catalog always
satisfies `net + tax = price after discount`, and large or exotic-exponent amounts are taxed
exactly.

### Order status & lifecycle

Orders move through a guarded state machine. The statuses are `New`, `InProgress`, `Paid`,
`Fulfilled`, `Canceled`, and `Refunded`, with these allowed transitions:

```
New        → InProgress | Canceled
InProgress → Paid | Canceled
Paid       → Fulfilled | Refunded
Fulfilled  → Refunded
Canceled, Refunded   (terminal)
```

Transition through `Shops::order($order)` or the helper methods on the order (they call the
same manager); each one validates the move under the order's row lock, stamps the matching
timestamp column (`in_progress_at`, `paid_at`, `fulfilled_at`, `canceled_at`, `refunded_at`),
persists, and fires events:

```php
use RoundlyConsulting\Shops\Orders\Enums\Status;

Shops::order($order)->transition(Status::InProgress);
Shops::order($order)->fulfil();   // Paid → Fulfilled
Shops::order($order)->cancel();
Shops::order($order)->refund();

$order->markInProgress();   // New → InProgress
$order->markPaid();         // InProgress → Paid, stamps paid_at, fires OrderPaid
$order->markFulfilled();    // Paid → Fulfilled, fires OrderFulfilled
$order->cancel();           // → Canceled, fires OrderCanceled; returns applied store credit
$order->refund();           // Paid|Fulfilled → Refunded, fires OrderRefunded

// Or transition explicitly:
$order->transitionTo(Status::Paid);

// Reads:
$order->status->is(Status::Paid);                       // bool
$order->status->isIn([Status::Paid, Status::Fulfilled]); // bool
$order->status->canTransitionTo(Status::Refunded);       // bool
$order->status->isTerminal();                            // bool
```

An illegal transition (e.g. refunding a `New` order) throws
`RoundlyConsulting\Shops\Orders\Exceptions\IllegalStatusTransitionException` and changes
nothing. A transition that fails part-way (a fulfilment whose stock cannot be sold) changes
nothing either — not the row and not the order instance you hold, so retrying it on the same
instance works. Every transition fires `OrderStatusChanged` (carrying `from`/`to`) plus a
status-specific event (`OrderPaid`, `OrderFulfilled`, `OrderCanceled`, `OrderRefunded`)
your application can listen to. Order events (and `OrderPlaced`) fire **after the surrounding
transaction commits**, so a rolled-back transition or checkout fires nothing.

What each move does to stock and store credit: canceling releases the reservation and returns
any store credit applied to the order; refunding a `Paid` (not yet fulfilled) order releases the
reservation; fulfilling sells it; refunding a `Fulfilled` order leaves stock alone.

### Order numbers

By default order numbers are `<two-digit year><6-digit sequence>` (e.g. `24000001`),
sequenced per year and counting soft-deleted orders. A number is assigned once, when the order
is first inserted (unless you set one) — never when orders are loaded. `orders.number` is
unique across all orders (orders route-bind by it): the default generator skips numbers that
are already taken, and when two checkouts race to the same generated number the second insert
is refused by the index and simply asks the generator again (up to 5 times, in a savepoint). An
explicitly set number is never replaced — a duplicate throws. Provide your own strategy by
implementing `NumberGenerator` and pointing `shops.orders.number_generator` at it:

```php
use RoundlyConsulting\Shops\Orders\NumberGenerators\NumberGenerator;
use RoundlyConsulting\Shops\Orders\Order;

final class UuidNumberGenerator implements NumberGenerator
{
    public function generate(Order $order): string
    {
        return (string) \Illuminate\Support\Str::uuid();
    }
}
```

### Product media

Products, variants, and categories carry images via `media-library-for-laravel`. Catalog media
is **public** by default (CDN/SEO friendly) and generates responsive variants from the width
ladder in `shops.media`, each named `responsive-<width>` (`responsive-320`, `responsive-640`, …).

```php
use Illuminate\Http\UploadedFile;

$product->addMedia($file)->toMediaBucket($product->featuredBucket()); // single featured image
$product->addMedia($file)->toMediaBucket($product->galleryBucket());  // multi-image gallery

$product->featuredImageUrl();                   // the original
$product->featuredImageUrl('responsive-640');   // a responsive variant from the width ladder
$product->galleryUrls();              // list<string>
$product->seoImageUrl();              // featured, falling back to the first gallery image

$variant->addMedia($file)->toMediaBucket($variant->variantGalleryBucket());
$variant->variantImageUrl();

$category->addMedia($file)->toMediaBucket($category->bannerBucket());
$category->bannerUrl();
```

Every reader takes an optional variant name and serves the original image when that variant has
not been generated (an image narrower than the width, or a queued conversion that has not run
yet), so a page never fails on a missing variant. Bucket names, disk, visibility, responsive
widths, and the max file size (bytes) are configured under `shops.media` and apply to every
catalog bucket — product, variant and category alike.

### Reviews

Products are reviewable via `reviews-for-laravel`, with rating aggregates:

```php
$product->addReview($user)->rating(5)->title('Great')->content('Love it')->approved()->create();

$product->approvedReviewsCount(); // int
$product->averageRating();        // ?float (approved reviews only)
$product->ratingDistribution();   // [5 => 12, 4 => 3, ...]
$product->ratingSummary();        // RatingSummary{average, count, distribution}
```

**Verified purchase.** `$product->review($user)` pre-flags the review as a verified purchase
using the bound `VerifiedPurchaseResolver`. The default `NullVerifiedPurchaseResolver` never
verifies; point `shops.reviews.verified_purchase_resolver` at
`DatabaseVerifiedPurchaseResolver` to verify buyers with a **paid and fulfilled** order
containing the product (requires the order to carry a customer — see **Order customer**).

### Spec sheet (attributes)

Typed, validated, filterable product attributes via `attributes-for-laravel` — the descriptive
spec sheet that complements variant *options* (which define SKUs). Define the schema under
`shops.attributes.definitions`:

```php
// config/shops.php
'attributes' => ['definitions' => [
    'material' => ['type' => 'string'],
    'weight'   => ['type' => 'integer', 'rules' => ['min:0']],
]],
```

```php
$product->attachAttribute('material', 'wool');
$product->attachAttribute('weight', 500);
$product->attr('material')->string(); // 'wool'
$product->attr('weight')->int();      // 500

Product::query()->whereAttribute('material', 'wool')->get();
Product::query()->whereAttributeBetween('weight', 100, 500)->get();
Product::query()->orderByAttribute('weight', 'desc')->get();
```

With `attributes.strict` enabled, attaching an undefined attribute throws
`UnknownAttributeException` and an ill-typed value throws `InvalidAttributeValueException`.

### Coupons & discounts

Coupons are powered entirely by `coupons-for-laravel`. Create coupons with that package, then
reference them by **code** — shops resolves the discount through the bound `DiscountResolver`
and records the redemption at place-order:

```php
use RoundlyConsulting\Coupons\Facades\Coupons;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Shops\Facades\Shops;
use RoundlyConsulting\Money\Money;

$coupon = Coupons::generate(DiscountType::Percentage, 1000, 'WELCOME10'); // basis points: 10 %
$coupon->activate()->save();

// Preview a discount (no redemption):
$result = Shops::coupons()->preview('WELCOME10', Money::ofMinor(1000, 'EUR'));
$result->discount; // 1.00 EUR off
$result->source;   // the coupon as a money Discount, to compose in a DiscountStack

// Price a cart with a code (defaults to the cart's stored coupon_code):
Shops::cart($cart)->price('WELCOME10')->getFinalPrice();
```

At place-order the coupon is linked to the order and redeemed once for the buyer, and the
discount it grants is **snapshotted** onto the order (`discount` in the order currency,
`free_shipping`, `coupon_code`). The order's price reads that snapshot, so expiring, revoking or
exhausting the coupon afterwards — including the single use this order consumed — never changes
what the order costs (free-shipping coupons zero the shipping line). Swap the coupon model or
resolver via `shops.discounts`.

### Pay with store credit

With `credits-for-laravel`, buyers can pay all or part of an order from a store-credit bucket
**denominated in the order's currency**. The buyer model must **implement
`RoundlyConsulting\Credits\Interfaces\Creditable`** and use credits' `HasCredits` trait — the
trait alone is not enough: an order whose customer is not `Creditable` is charged in full through
the gateway, with no credit applied.

```php
use RoundlyConsulting\Credits\Interfaces\Creditable;
use RoundlyConsulting\Credits\Traits\HasCredits;

class User extends Authenticatable implements Creditable
{
    use HasCredits;
}
```

Map the bucket in `config/credits.php`:

```php
'currencies' => ['store_credit' => 'EUR'], // your shop currency; one bucket per currency
```

Enable `shops.payment.allow_store_credit` (env `SHOPS_ALLOW_STORE_CREDIT`);
`Shops::order($order)->charge()` then debits available credit before charging the gateway for the remainder
(`$order->gatewayAmount()`) — an order credit covers in full is marked paid without a gateway
charge. A **declined** charge keeps none of the credit it applied (the whole charge rolls back),
and **canceling** an order returns any credit applied to it — also credit you applied yourself
with `StoreCreditTender::apply()` — so a buyer never loses credit to an order that was never paid.
The bucket is
`shops.payment.store_credit_bucket`, and refunds can be returned as store credit via
`shops.payment.refund_to_store_credit`.

```php
use RoundlyConsulting\Shops\Payments\StoreCreditTender;

$remainder = app(StoreCreditTender::class)->apply($order, $customer); // Money still owed
$remainder = app(StoreCreditTender::class)->apply($order, $customer, Money::ofMinor(500, 'EUR')); // cap the debit

$order->store_credit_applied; // ?Money, in the order currency

app(StoreCreditTender::class)->restore($order); // ?Money — hand it back (cancel() does this)
```

An undenominated bucket throws `StoreCreditBucketNotDenominated` and a bucket in another
currency `StoreCreditCurrencyMismatch` (both before any credit moves). The refund listener
skips such a bucket with a `Log::warning()` instead — the refund has already happened.

### Customer address book

With `addresses-for-laravel`, build an order's billing/shipping snapshot from a customer's saved
addresses. Your customer model must **implement `RoundlyConsulting\Addresses\Contracts\Addressable`**
and use the `HasAddresses` trait (`PlaceOrderData::fromAddressBook()` and
`Shops::addresses()->defaults()` type-hint the interface — the trait alone is a `TypeError`):

```php
use RoundlyConsulting\Addresses\Contracts\Addressable;
use RoundlyConsulting\Addresses\Traits\HasAddresses;

class User extends Authenticatable implements Addressable
{
    use HasAddresses;
}
```

Then:

```php
use RoundlyConsulting\Shops\Orders\DataTransferObjects\PlaceOrderData;

$order = Shops::cart($cart)->checkout(PlaceOrderData::fromAddressBook($customer));

$defaults = Shops::addresses()->defaults($customer); // OrderAddresses
$defaults->shipping; // ?Address
$defaults->billing;  // ?Address
```

Shipping is taken from the customer's primary shipping address and billing from their primary
billing address; when no billing address exists, the shipping address is reused (toggle with
`shops.addresses.billing_same_as_shipping`). The order keeps snapshotting addresses — there is
no foreign key to the address book.

### Order customer

`Order` carries an optional polymorphic `customer` (the buyer), copied from the cart's owner at
place-order. It powers the address book, store credit, and verified-purchase reviews. It is
nullable, so guest orders are fully supported.

### Payment & shipping drivers

The package defines payment and shipping as **driver contracts** and ships no vendor SDK. Bind
your own implementation via config; the defaults work zero-config (a null gateway that always
succeeds, and free shipping):

```php
use RoundlyConsulting\Shops\Contracts\PaymentGateway;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Payments\PaymentResult;
use RoundlyConsulting\Money\Money;

final class StripeGateway implements PaymentGateway
{
    // Charge $order->gatewayAmount(): the total minus any store credit already applied.
    public function charge(Order $order): PaymentResult { /* … */ }
    public function refund(Order $order, Money $amount): PaymentResult { /* … */ }
}
```

Register it in `config/shops.php` (`payment.gateway` / `shipping.method`). Charge an order
through the bound gateway — on success it transitions the order to `Paid` — and quote its
shipping through the bound method:

```php
$result = Shops::order($order)->charge();                        // PaymentResult
$quote  = Shops::order($order)->quoteShipping($destinationAddress); // Money
```

`charge()` only charges a `New` or `InProgress` order, and decides that under the order's row
lock **before** any store credit or gateway call: charging a `Paid`, `Fulfilled`, `Canceled` or
`Refunded` order — a double-clicked "Pay", or a second request holding a stale copy — throws
`IllegalStatusTransitionException` with nothing charged. The whole charge (credit, gateway call,
move to `Paid`) runs in one transaction holding that lock, so a concurrent second charge waits
and is then refused. Keep your gateway's `charge()` to the payment call itself; `OrderPaid`
listeners run after the commit.

A failed (declined) charge leaves the order's status unchanged and keeps no store credit. A zero
balance (store credit covered the order, or it is free) skips the gateway and succeeds with a
zero amount. The amount charged includes the order's snapshotted shipping.

Refunds are **host-driven**: the package never calls the gateway's `refund()`. Refund through
your gateway (at most `$order->gatewayAmount()`), then transition the order:

```php
$result = app(PaymentGateway::class)->refund($order, $order->gatewayAmount());

if ($result->successful) {
    $order->refund(); // → Refunded, fires OrderRefunded
}
```

The gateway only took `gatewayAmount()`: return any store-credit share yourself
(`$customer->modifyCreditsMoney($order->store_credit_applied, bucket: …)`). With
`shops.payment.refund_to_store_credit` on, **skip the gateway refund** — `$order->refund()` alone
credits the whole order total back as store credit, and doing both refunds the buyer twice.

## Testing

```bash
composer test
```

In your application's tests, `Shops::fake()` records every cart change, checkout, charge,
transition and stock adjustment — see [Faking it in your tests](#faking-it-in-your-tests).

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=shops-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=shops-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
