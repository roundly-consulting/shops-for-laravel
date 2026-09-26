<?php

declare(strict_types=1);

use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Cart\Actions\AddToCart;
use RoundlyConsulting\Shops\Cart\Actions\UpdateCartItem;
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Cart\CartItem;
use RoundlyConsulting\Shops\Exceptions\InvalidQuantityException;
use RoundlyConsulting\Shops\Inventory\Actions\AdjustStockAction;
use RoundlyConsulting\Shops\Inventory\Enums\StockReason;
use RoundlyConsulting\Shops\Inventory\StockAdjustment;
use RoundlyConsulting\Shops\Orders\Actions\AddOrderItemAction;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\PriceLine;
use RoundlyConsulting\Shops\Orders\Item;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Products\ProductVariant;
use RoundlyConsulting\Shops\Support\Quantity;

/**
 * A quantity is a count of items: a whole number, at least one, and no more than the
 * `smallint` quantity columns hold. Every entry point refuses anything else with a typed
 * exception before it writes — a zero or negative line silently produced zero or negative
 * totals, and an out-of-range one was a database error on Postgres/MySQL but stored as-is on
 * SQLite. Stock adjustments are signed, but each reason has a direction.
 */
beforeEach(function (): void {
    config()->set('shops.pricing.default_currency', 'EUR');
});

function quantityCart(): Cart
{
    return Cart::create(['currency' => 'EUR']);
}

function quantityVariant(): ProductVariant
{
    return ProductVariant::factory()->withEurPrice('1000')->create(['stock' => 10]);
}

it('refuses to add a non-positive quantity to a cart', function (int $quantity): void {
    $cart = quantityCart();

    expect(fn () => $cart->add(quantityVariant(), $quantity))
        ->toThrow(InvalidQuantityException::class, "at least 1, got {$quantity}")
        ->and($cart->items()->count())->toBe(0);
})->with([0, -1, -5]);

it('refuses to shrink an existing cart line through add()', function (): void {
    $cart = quantityCart();
    $variant = quantityVariant();
    $cart->add($variant, 3);

    // add() merges into the existing line: -2 would have "worked" (3 → 1) and 0 did nothing.
    expect(fn () => $cart->add($variant, -2))->toThrow(InvalidQuantityException::class)
        ->and($cart->items()->sole()->quantity)->toBe(3);
});

it('refuses a cart line beyond the quantity column range', function (): void {
    $cart = quantityCart();
    $variant = quantityVariant();
    $cart->add($variant, Quantity::MAX - 1);

    expect(fn () => $cart->add($variant, 2))->toThrow(InvalidQuantityException::class, 'at most '.Quantity::MAX)
        ->and($cart->items()->sole()->quantity)->toBe(Quantity::MAX - 1)
        ->and(fn () => quantityCart()->add($variant, Quantity::MAX + 1))->toThrow(InvalidQuantityException::class);
});

it('refuses a non-positive quantity through the AddToCart action', function (): void {
    expect(fn () => app(AddToCart::class)->execute(quantityCart(), quantityVariant(), 0))
        ->toThrow(InvalidQuantityException::class);
});

it('removes a cart line set to zero but refuses a negative or out-of-range quantity', function (): void {
    $cart = quantityCart();
    $item = $cart->add(quantityVariant(), 2);

    expect(fn () => app(UpdateCartItem::class)->execute($item, -1))->toThrow(InvalidQuantityException::class)
        ->and(fn () => app(UpdateCartItem::class)->execute($item, Quantity::MAX + 1))->toThrow(InvalidQuantityException::class)
        ->and($item->refresh()->quantity)->toBe(2)
        // Zero is the documented "remove this line".
        ->and(app(UpdateCartItem::class)->execute($item, 0))->toBeNull()
        ->and($cart->items()->count())->toBe(0);
});

it('refuses a non-positive quantity on an order line', function (int $quantity): void {
    $order = Order::factory()->create();

    expect(fn () => app(AddOrderItemAction::class)->execute($order, quantityVariant(), $quantity))
        ->toThrow(InvalidQuantityException::class)
        ->and($order->items()->count())->toBe(0);
})->with([0, -3]);

it('refuses an invalid quantity written straight to a line model', function (mixed $quantity): void {
    $order = Order::factory()->create();

    expect(fn () => Item::create([
        'order_id' => $order->id,
        'name' => 'Water',
        'quantity' => $quantity,
        'price' => Money::ofMinor(199, 'EUR'),
    ]))->toThrow(InvalidQuantityException::class)
        ->and(fn () => CartItem::factory()->create(['quantity' => $quantity]))->toThrow(InvalidQuantityException::class)
        ->and($order->items()->count())->toBe(0);
})->with([
    'zero' => [0],
    'negative' => [-2],
    'fraction' => [2.5],
    'fractional string' => ['2.5'],
    'word' => ['two'],
    'too large' => [Quantity::MAX + 1],
]);

it('accepts an integer string quantity on a line model, as request input arrives', function (): void {
    $order = Order::factory()->create();

    $item = Item::create([
        'order_id' => $order->id,
        'name' => 'Water',
        'quantity' => '3',
        'price' => Money::ofMinor(199, 'EUR'),
    ]);

    expect($item->quantity)->toBe(3)
        ->and($order->refresh()->price->getSubtotal()->minor())->toBe('597');
});

it('refuses a non-positive quantity in a price line', function (int $quantity): void {
    expect(fn () => new PriceLine(Money::ofMinor(1000, 'EUR'), $quantity))
        ->toThrow(InvalidQuantityException::class);
})->with([0, -1]);

it('refuses a stock delta whose sign contradicts its reason', function (StockReason $reason, int $delta): void {
    $variant = quantityVariant();

    expect(fn () => app(AdjustStockAction::class)->execute($variant, $delta, $reason))
        ->toThrow(InvalidQuantityException::class)
        ->and($variant->refresh()->stock)->toBe(10)
        ->and($variant->reserved)->toBe(0)
        ->and(StockAdjustment::query()->count())->toBe(0);
})->with([
    'received negative' => [StockReason::Received, -5],
    'returned negative' => [StockReason::Returned, -1],
    'reserved negative' => [StockReason::Reserved, -1],
    'sold positive' => [StockReason::Sold, 1],
    'released positive' => [StockReason::Released, 1],
    'zero manual' => [StockReason::Manual, 0],
    'zero received' => [StockReason::Received, 0],
]);

it('accepts a manual correction in either direction', function (): void {
    $variant = quantityVariant();

    app(AdjustStockAction::class)->execute($variant, 5, StockReason::Manual);
    app(AdjustStockAction::class)->execute($variant, -3, StockReason::Manual);

    expect($variant->refresh()->stock)->toBe(12);
});
