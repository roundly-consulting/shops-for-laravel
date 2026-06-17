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

$product = Product::create([
    'name' => 'Sparkling Water',
    'price' => 199,        // stored as the minor unit (cents)
    'currency' => 'EUR',
]);

$product->categories()->attach($category);

$product->price; // RoundlyConsulting\Shops\Support\Money\Money (199 EUR)
```

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

$price->price;                  // sum of item prices
$price->getPriceAfterDiscount(); // after the coupon (if any)
$price->getTaxPrice();           // tax on (discounted price + shipping)
$price->getFinalPrice();         // discounted price + shipping + tax
$price->getDiscountValue();      // amount saved by the coupon
```

### Order status

```php
use RoundlyConsulting\Shops\Orders\Enums\Status;

$order->status->is(Status::Completed);                       // bool
$order->status->isIn([Status::InProgress, Status::Completed]); // bool
```

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
