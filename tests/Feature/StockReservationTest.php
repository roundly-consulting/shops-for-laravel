<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Inventory\Exceptions\InsufficientStockException;
use RoundlyConsulting\Shops\Orders\Actions\AddOrderItemAction;
use RoundlyConsulting\Shops\Orders\Actions\ReserveStockAction;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Products\ProductVariant;

beforeEach(function (): void {
    config()->set('shops.pricing.default_currency', 'EUR');
});

$orderWithVariant = function (ProductVariant $variant, int $quantity): Order {
    $order = Order::factory()->create();
    app(AddOrderItemAction::class)->execute($order, $variant, $quantity);

    return $order;
};

it('reserves stock for each order line', function () use ($orderWithVariant): void {
    $variant = ProductVariant::factory()->withEurPrice('1000')->create(['stock' => 10, 'reserved' => 0]);
    $order = $orderWithVariant($variant, 3);

    app(ReserveStockAction::class)->execute($order);

    expect($variant->refresh())->reserved->toBe(3)->stock->toBe(10);
});

it('aborts the whole reservation when one line oversells', function () use ($orderWithVariant): void {
    $variant = ProductVariant::factory()->withEurPrice('1000')->create(['stock' => 2, 'reserved' => 0]);
    $order = $orderWithVariant($variant, 5);

    expect(fn () => app(ReserveStockAction::class)->execute($order))
        ->toThrow(InsufficientStockException::class);

    expect($variant->refresh()->reserved)->toBe(0);
});

it('ignores order lines without a variant', function () use ($orderWithVariant): void {
    $variant = ProductVariant::factory()->withEurPrice('1000')->create(['stock' => 5]);
    $order = $orderWithVariant($variant, 1);
    $order->items()->first()->update(['product_variant_id' => null]);

    app(ReserveStockAction::class)->execute($order);

    expect($variant->refresh()->reserved)->toBe(0);
});

it('releases reserved stock when an order is canceled', function () use ($orderWithVariant): void {
    $variant = ProductVariant::factory()->withEurPrice('1000')->create(['stock' => 10]);
    $order = $orderWithVariant($variant, 4);
    app(ReserveStockAction::class)->execute($order);

    $order->cancel();

    expect($variant->refresh())->reserved->toBe(0)->stock->toBe(10);
});

it('converts a reservation into a sale when an order is fulfilled', function () use ($orderWithVariant): void {
    $variant = ProductVariant::factory()->withEurPrice('1000')->create(['stock' => 10]);
    $order = $orderWithVariant($variant, 4);
    app(ReserveStockAction::class)->execute($order);

    $order->markInProgress()->markPaid()->markFulfilled();

    expect($variant->refresh())->reserved->toBe(0)->stock->toBe(6);
});

it('skips release for order lines without a variant on cancel', function () use ($orderWithVariant): void {
    $variant = ProductVariant::factory()->withEurPrice('1000')->create(['stock' => 10]);
    $order = $orderWithVariant($variant, 2);
    app(ReserveStockAction::class)->execute($order);
    $order->items()->first()->update(['product_variant_id' => null]);

    $order->cancel();

    // The detached line is ignored, so its reservation stays held.
    expect($variant->refresh()->reserved)->toBe(2);
});
