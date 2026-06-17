# Shops for Laravel

Manage single or multiple shops with products, categories, and orders in Laravel. The
package ships Eloquent models for products, categories, orders, and order items, a native
money value object, automatic slugs and order numbers, and an optional, pluggable coupon
discount calculation — with **no third-party runtime dependencies** beyond Laravel itself.

## Requirements

- PHP 8.4+
- Laravel 12.0 or 13.0

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

The published `config/shops.php` contains:

```php
return [
    'tax_rate' => (int) env('SHOPS_TAX_RATE', 20),

    'orders' => [
        'number_generator' => DefaultNumberGenerator::class,
        'coupon_model' => env('SHOPS_COUPON_MODEL'),
    ],
];
```

| Key | Type | Default | Env | Description |
|---|---|---|---|---|
| `tax_rate` | `int` | `20` | `SHOPS_TAX_RATE` | Whole-number percentage tax applied to an order's price after discount and shipping. |
| `orders.number_generator` | `class-string` | `DefaultNumberGenerator::class` | — | The class used to generate an order number. Must implement `NumberGenerator`. |
| `orders.coupon_model` | `class-string\|null` | `null` | `SHOPS_COUPON_MODEL` | The Eloquent model backing an order's coupon relation. Must implement the `Coupon` contract. Leave `null` if you do not use coupons. |

## Usage

### Products and categories

Products and categories belong to an optional polymorphic `shop` (so the same tables serve a
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
are resolved per line by tax class via the `tax_classes` config map; host applications can
supply jurisdiction logic by binding their own `TaxResolver` implementation.

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

### Coupons

Coupons live in your own application (or another package). To enable discounts, implement the
`Coupon` contract on your coupon model and register it via `shops.orders.coupon_model`:

```php
use RoundlyConsulting\Shops\Contracts\Coupon;
use RoundlyConsulting\Shops\Support\Money\Money;

final class AppCoupon extends \Illuminate\Database\Eloquent\Model implements Coupon
{
    public function canBeApplied(): bool
    {
        return $this->usage < $this->max_usage;
    }

    public function apply(Money $money): Money
    {
        return $money->subtract($money->multiply($this->value)->divide(100));
    }

    public function recordUsage(): void
    {
        $this->increment('usage');
    }
}
```

Apply a coupon (and record its usage transactionally) with the `UseCoupon` action:

```php
use RoundlyConsulting\Shops\Products\Actions\UseCoupon;
use RoundlyConsulting\Shops\Support\Money\Money;

$discounted = app(UseCoupon::class)->execute($coupon, Money::EUR(1000));
```

If the coupon cannot be applied, the original amount is returned unchanged and no usage is
recorded.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
