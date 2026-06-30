<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/shops-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=shops-for-laravel">
    <img src="art/hero.png" alt="Shops for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

# Shops for Laravel

A production-grade e-commerce foundation for Laravel: products with variants, SKUs and
options, a race-safe inventory ledger with stock reservations, a guarded order state machine,
a persistent cart with one-call order placement, correct net/gross tax pricing, native
per-locale translations, and payment & shipping **driver contracts**. Catalog media, product
reviews, spec-sheet attributes, coupons, store credit, and customer address books are powered
by sibling roundly-consulting packages (see **Integrates with**).

## Requirements

- PHP 8.4+
- Laravel 12.0 or 13.0

## Integrates with

Shops builds directly on these roundly-consulting packages (installed automatically as
dependencies):

| Package | What it powers in shops |
|---|---|
| [`enums-for-laravel`](https://github.com/roundly-consulting/enums-for-laravel) | Order `Status`, `PriceType` and `StockReason` get `labels()`/`options()`/`validationRule()` and other helpers |
| [`media-library-for-laravel`](https://github.com/roundly-consulting/media-library-for-laravel) | Product featured/gallery images, per-variant images, category banners (public, responsive) |
| [`reviews-for-laravel`](https://github.com/roundly-consulting/reviews-for-laravel) | Product reviews, rating aggregates, verified-purchase gating |
| [`attributes-for-laravel`](https://github.com/roundly-consulting/attributes-for-laravel) | Typed, filterable product spec-sheet attributes |
| [`coupons-for-laravel`](https://github.com/roundly-consulting/coupons-for-laravel) | All coupon/discount logic (percentage, fixed, free shipping, caps, usage limits) |
| [`credits-for-laravel`](https://github.com/roundly-consulting/credits-for-laravel) | "Pay with store credit" tender and store-credit refunds |
| [`addresses-for-laravel`](https://github.com/roundly-consulting/addresses-for-laravel) | Customer address book → order billing/shipping snapshot |

See `docs/cross-package-integration-plan.md` for the org-wide tier map. Coupon logic now lives
entirely in `coupons-for-laravel`; shops no longer ships its own coupon model.

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

The migrations are also auto-discovered, so the package works without publishing them if you
prefer to keep them inside the package.

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
        'coupon_model' => env('SHOPS_COUPON_MODEL'),
    ],
];
```

| Key | Type | Default | Env | Description |
|---|---|---|---|---|
| `shop_model` | `class-string` | `Shops\Shop::class` | `SHOPS_SHOP_MODEL` | The Eloquent tenant model every owned record points at via its `shop_id` foreign key. Swap in your own model to extend it. |
| `pricing.price_type` | `string` | `gross` | `SHOPS_PRICE_TYPE` | `gross` (tax is extracted from the stored price) or `net` (tax is added on top). |
| `pricing.default_currency` | `string` | `EUR` | `SHOPS_DEFAULT_CURRENCY` | ISO-4217 currency every cart and order uses. |
| `tax_classes` | `array<string,int>` | `standard 20, reduced 10, zero 0` | `SHOPS_TAX_RATE` (standard) | Whole-percent fallback floor used when a shop has no matching database tax rate. |
| `tax.resolver` | `class-string` | `DatabaseTaxResolver::class` | — | Resolves the rate for a `(shop, class, country)` lookup. Defaults to per-shop database rates with the config map as the floor. Bind `ConfigTaxResolver` to use only the config map, or your own `TaxResolver`. |
| `orders.number_generator` | `class-string` | `DefaultNumberGenerator::class` | — | The class used to generate an order number. Must implement `NumberGenerator`. |
| `orders.coupon_model` | `class-string\|null` | `null` | `SHOPS_COUPON_MODEL` | The Eloquent model backing an order's coupon relation. Must implement the `Coupon` contract. Leave `null` if you do not use coupons. |

## Usage

### Shop facade

The optional `Shop` facade is a discoverable entry point fronting the package's actions. The
underlying `ShopManager` is also resolvable for dependency injection:

```php
use RoundlyConsulting\Shops\Facades\Shop;

$order = Shop::placeOrder($cart, $placeOrderData);
$order = Shop::transition($order, Status::Paid);
$result = Shop::charge($order);
$discounted = Shop::useCoupon($coupon, Money::EUR(1000));
```

### Query scopes & route binding

```php
Product::query()->published();          // published_at set and not in the future
Product::query()->unpublished();
Product::query()->forShop($shop);       // filter by a Shop model or a raw shop id
ProductVariant::query()->inStock(2);    // variants with >= 2 available (or untracked)
```

Orders are route-bound by their `number`; products, categories and shops by their `slug`.

### Shops (tenancy)

A `Shop` is the concrete tenant every owned record belongs to through a plain `shop_id`
foreign key. A single-shop app can ignore it entirely (the column is nullable); a multi-shop
app creates shops and scopes data to them.

```php
use RoundlyConsulting\Shops\Shops\Shop;
use RoundlyConsulting\Shops\Shops\CurrentShop;

$shop = Shop::create(['name' => 'Acme EU', 'currency' => 'EUR']);
$shop->currency();   // 'EUR' — the per-shop override, or the configured default when null

// Explicit ownership always wins:
$product = Product::create(['shop_id' => $shop->id, 'name' => 'Sparkling Water']);

// Or bind a current shop and let ownership auto-fill on create:
app(CurrentShop::class)->set($shop);
Product::create(['name' => 'Still Water']);          // shop_id auto-filled
app(CurrentShop::class)->forget();

// Scoped block — restores the previous binding afterwards (even on exception):
app(CurrentShop::class)->run($shop, function () {
    Product::create(['name' => 'Tonic']);            // belongs to $shop
});

Shop::current();                         // ?Shop bound to the current context

Product::query()->forShop($shop)->get();       // scope by model
Product::query()->forShop($shop->id)->get();   // or by id
```

Swap the tenant model by pointing `shops.shop_model` at your own class.

### Per-shop tax rates

Each shop owns many `TaxRate` rows. A rate carries a tax class, an optional ISO-3166-1
alpha-2 country, and a rate in **integer basis points** (1 bp = 0.01%, so `1900` = 19.00%,
`850` = 8.5%). The default `DatabaseTaxResolver` resolves a `(shop, class, country)` lookup
through a fallback chain: an exact country match, then the shop's class default, then the
highest-priority class rate, then the `tax_classes` config floor, then zero.

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
$value->percent();        // 8.5
$value->basisPoints;      // 850
$value->grossDivisor();   // 1.085
$value->isZero();         // false

app(TaxResolver::class)->rateFor($shop, 'standard');   // shop's default standard rate
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
use RoundlyConsulting\Shops\Support\Money\Money;

$category = Category::create(['name' => 'Beverages']);
// $category->slug === 'beverages'

$product = Product::create(['name' => 'Sparkling Water']);
$product->categories()->attach($category);

$product->price; // RoundlyConsulting\Shops\Support\Money\Money — proxied from the default variant
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

### Variants, SKUs and options

Every sellable unit is a `ProductVariant` with its own `sku`, `price`, `currency`, tax class,
and stock. A product created without explicit variants automatically gets **one default
variant**, so simple single-SKU products stay a one-liner; `$product->price` proxies the
default variant's price.

```php
use RoundlyConsulting\Shops\Support\Money\Money;

// Add explicit variants:
$small = $product->variants()->create([
    'sku' => 'WATER-0.5L', 'price' => Money::EUR(199), 'currency' => 'EUR', 'stock' => 50,
]);
$large = $product->variants()->create([
    'sku' => 'WATER-1L', 'price' => Money::EUR(299), 'currency' => 'EUR', 'stock' => 30,
]);

$product->defaultVariant;       // lowest-position variant
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
`stock` (on hand) and `reserved` (held for pending orders). All writes go through
`AdjustStockAction`, which row-locks the variant inside a transaction to avoid oversell:

```php
use RoundlyConsulting\Shops\Inventory\Actions\AdjustStockAction;
use RoundlyConsulting\Shops\Inventory\Enums\StockReason;

app(AdjustStockAction::class)->execute($variant, 100, StockReason::Received);   // +100 on hand
app(AdjustStockAction::class)->execute($variant, -1, StockReason::Sold, $order); // sell one
```

Selling below available stock on a `track_stock` variant throws `InsufficientStockException`;
variants with `track_stock = false` (digital/unlimited goods) never throw. Every adjustment
fires `StockAdjusted`, and crossing `shops.inventory.low_stock_threshold` fires `StockRanLow`.

Orders manage reservations automatically: `ReserveStockAction` holds each line's quantity when
an order is placed, canceling an order **releases** the hold, and fulfilling an order
**converts** the reservation into a sale (decrementing on-hand stock). An oversell during
reservation rolls back the whole order and holds nothing.

### Money value object

`Money` is an immutable value object storing an integer amount in the currency's minor unit.
It powers the `price` cast on products and order items, with no third-party money dependency.

```php
use RoundlyConsulting\Shops\Support\Money\Money;

$price = Money::EUR(1000);                 // 10.00 EUR
$total = Money::sum(Money::EUR(1000), Money::EUR(500)); // 1500 EUR
$withTax = $price->multiply(120)->divide(100);          // 1200 EUR

$price->getAmount();              // "1000"
$price->getCurrency()->getCode(); // "EUR"
```

Combining different currencies throws a `CurrencyMismatchException`; an invalid currency code
throws an `InvalidCurrencyException`.

### Orders and pricing

### Cart & placing an order

A `Cart` is a persistent basket with an optional polymorphic `owner` (a user) or a guest
`token` for anonymous checkout, plus a configured currency. Add variants to it and read its
price through the same engine orders use:

```php
use RoundlyConsulting\Shops\Cart\Cart;

$cart = Cart::create(['currency' => 'EUR']);
$cart->add($variant, quantity: 2);   // snapshots name/sku/price; same variant increments

$cart->subtotal();   // Money
$cart->price();      // Price DTO (pass a coupon to discount it)
```

`PlaceOrderAction` turns a cart into an order in one transaction — snapshotting each line,
reserving stock, linking a coupon by code, storing the billing/shipping address, generating the
number, firing `OrderPlaced`, and clearing the cart. An oversell rolls everything back and
leaves the cart untouched:

```php
use RoundlyConsulting\Shops\Orders\Actions\PlaceOrderAction;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Address;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\PlaceOrderData;

$order = app(PlaceOrderAction::class)->execute($cart, new PlaceOrderData(
    billing: new Address('Ada Lovelace', '1 Analytical Way', 'London', 'EC1', 'GB'),
    couponCode: 'WELCOME10',
));
```

Billing and shipping addresses are stored as JSON and cast to an immutable `Address` DTO.

### Orders and pricing

An order has many `items`, an optional `coupon`, and an automatically generated `number`. Its
`price` accessor returns a `Price` DTO that computes discount, shipping, tax, and the final
total from the order's items.

```php
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Orders\Item;
use RoundlyConsulting\Shops\Support\Money\Money;

$order = Order::create([]);              // number auto-generated, e.g. "24000001"

Item::create([
    'order_id' => $order->id,
    'name' => 'Sparkling Water',
    'quantity' => 2,
    'price' => 199,
    'currency' => 'EUR',
]);

$price = $order->price;                  // RoundlyConsulting\Shops\Orders\DataTransferObjects\Price

$price->getSubtotal();           // sum of line totals (quantity-aware), before discount
$price->getPriceAfterDiscount(); // after the coupon (if any)
$price->getNetPrice();           // tax-exclusive value of the goods
$price->getTaxPrice();           // tax across the goods (after discount)
$price->getDiscountValue();      // amount saved by the coupon
$price->getFinalPrice();         // amount the customer pays (goods + shipping, tax-correct)
```

Pricing is **quantity-aware** (a 2× line is billed twice) and **tax-correct** for both
tax-inclusive and tax-exclusive catalogs. Set `shops.pricing.price_type` to `gross`
(default — tax is *extracted* from the price) or `net` (tax is *added on top*). Tax rates
are resolved per line by the owning shop's database rates (with country awareness), falling
back to the `tax_classes` config map; host applications can supply their own jurisdiction
logic by binding a custom `TaxResolver`.

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

Transition with the helper methods on the order; each one validates the move, stamps the
matching timestamp column (`in_progress_at`, `paid_at`, `fulfilled_at`, `canceled_at`,
`refunded_at`), persists, and fires events:

```php
use RoundlyConsulting\Shops\Orders\Enums\Status;

$order->markInProgress();   // New → InProgress
$order->markPaid();         // InProgress → Paid, stamps paid_at, fires OrderPaid
$order->markFulfilled();    // Paid → Fulfilled, fires OrderFulfilled
$order->cancel();           // → Canceled, fires OrderCanceled
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
nothing. Every transition fires `OrderStatusChanged` (carrying `from`/`to`) plus a
status-specific event (`OrderPaid`, `OrderFulfilled`, `OrderCanceled`, `OrderRefunded`)
your application can listen to.

### Order numbers

By default order numbers are `<two-digit year><6-digit sequence>` (e.g. `24000001`),
sequenced per year and counting soft-deleted orders. Provide your own strategy by
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
ladder in `shops.media`.

```php
use Illuminate\Http\UploadedFile;

$product->addMedia($file)->toMediaBucket($product->featuredBucket()); // single featured image
$product->addMedia($file)->toMediaBucket($product->galleryBucket());  // multi-image gallery

$product->featuredImageUrl('detail'); // a named responsive variant
$product->galleryUrls();              // list<string>
$product->seoImageUrl();              // featured, falling back to the first gallery image

$variant->addMedia($file)->toMediaBucket($variant->variantGalleryBucket());
$variant->variantImageUrl();

$category->addMedia($file)->toMediaBucket($category->bannerBucket());
$category->bannerUrl();
```

Bucket names, disk, visibility, responsive widths, and max file size are configured under
`shops.media`.

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
use RoundlyConsulting\Shops\Facades\Shop;
use RoundlyConsulting\Shops\Support\Money\Money;

$coupon = Coupons::generate(DiscountType::Percentage, 10, 'WELCOME10');
$coupon->activate()->save();

// Preview a discount (no redemption):
Shop::discountFor('WELCOME10', Money::EUR(1000))->discount; // 100 EUR off

// Price a cart with a code (defaults to the cart's stored coupon_code):
$cart->price('WELCOME10')->getFinalPrice();
```

At place-order the coupon is linked to the order and redeemed once for the buyer (free-shipping
coupons zero the shipping line). Swap the coupon model or resolver via `shops.discounts`.

### Pay with store credit

With `credits-for-laravel`, buyers can pay all or part of an order from a store-credit bucket.
Enable `shops.payments.allow_store_credit`; `ChargeOrderAction` then debits available credit
before charging the gateway for the remainder. Refunds can be returned as store credit via
`shops.payments.refund_to_store_credit`.

```php
use RoundlyConsulting\Shops\Payments\StoreCreditTender;

$remainder = app(StoreCreditTender::class)->apply($order, $customer); // Money still owed
```

### Customer address book

With `addresses-for-laravel`, build an order's billing/shipping snapshot from a customer's saved
addresses. Add `HasAddresses` to your customer model, then:

```php
use RoundlyConsulting\Shops\Orders\DataTransferObjects\PlaceOrderData;

$order = Shop::placeOrder($cart, PlaceOrderData::fromAddressBook($customer));
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
use RoundlyConsulting\Shops\Support\Money\Money;

final class StripeGateway implements PaymentGateway
{
    public function charge(Order $order): PaymentResult { /* … */ }
    public function refund(Order $order, Money $amount): PaymentResult { /* … */ }
}
```

Register it in `config/shops.php` (`payment.gateway` / `shipping.method`). Charge an order
through the bound gateway with `ChargeOrderAction` — on success it transitions the order to
`Paid`:

```php
use RoundlyConsulting\Shops\Orders\Actions\ChargeOrderAction;
use RoundlyConsulting\Shops\Orders\Actions\QuoteShippingAction;

$result = app(ChargeOrderAction::class)->execute($order); // PaymentResult
$quote  = app(QuoteShippingAction::class)->execute($order, $destinationAddress); // Money
```

A failed charge leaves the order's status unchanged.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
