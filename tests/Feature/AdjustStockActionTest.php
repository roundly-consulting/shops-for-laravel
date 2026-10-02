<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Shops\Actions\Inventory\AdjustStockAction;
use RoundlyConsulting\Shops\Inventory\Enums\StockReason;
use RoundlyConsulting\Shops\Inventory\Events\StockAdjusted;
use RoundlyConsulting\Shops\Inventory\Events\StockRanLow;
use RoundlyConsulting\Shops\Inventory\Exceptions\InsufficientStockException;
use RoundlyConsulting\Shops\Inventory\StockAdjustment;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Products\ProductVariant;

it('receives stock and records a ledger row', function (): void {
    $variant = ProductVariant::factory()->create(['stock' => 5]);

    $adjustment = app(AdjustStockAction::class)->execute($variant, 10, StockReason::Received);

    expect($variant->refresh()->stock)->toBe(15)
        ->and($adjustment)->toBeInstanceOf(StockAdjustment::class)
        ->quantity->toBe(10)
        ->reason->toBe(StockReason::Received);
});

it('throws when selling below available stock on a tracked variant', function (): void {
    $variant = ProductVariant::factory()->create(['stock' => 2]);

    expect(fn () => app(AdjustStockAction::class)->execute($variant, -3, StockReason::Sold))
        ->toThrow(InsufficientStockException::class);

    expect($variant->refresh()->stock)->toBe(2);
});

it('never throws for an untracked variant and ignores availability', function (): void {
    $variant = ProductVariant::factory()->untracked()->create(['stock' => 0]);

    app(AdjustStockAction::class)->execute($variant, -50, StockReason::Sold);

    expect($variant->refresh()->stock)->toBe(-50);
});

it('adjusts the reserved quantity for reserve and release reasons', function (): void {
    $variant = ProductVariant::factory()->create(['stock' => 10, 'reserved' => 0]);

    app(AdjustStockAction::class)->execute($variant, 4, StockReason::Reserved);
    expect($variant->refresh())->reserved->toBe(4)->stock->toBe(10);

    app(AdjustStockAction::class)->execute($variant, -4, StockReason::Released);
    expect($variant->refresh())->reserved->toBe(0)->stock->toBe(10);
});

it('never lets reserved fall below zero', function (): void {
    $variant = ProductVariant::factory()->create(['reserved' => 1]);

    app(AdjustStockAction::class)->execute($variant, -5, StockReason::Released);

    expect($variant->refresh()->reserved)->toBe(0);
});

it('associates an adjustment reference', function (): void {
    $variant = ProductVariant::factory()->create(['stock' => 5]);
    $order = Order::factory()->create();

    $adjustment = app(AdjustStockAction::class)->execute($variant, 1, StockReason::Received, $order, 'restock');

    expect($adjustment->reference->is($order))->toBeTrue()
        ->and($adjustment->note)->toBe('restock');
});

it('fires the stock adjusted event', function (): void {
    Event::fake([StockAdjusted::class]);
    $variant = ProductVariant::factory()->create(['stock' => 5]);

    app(AdjustStockAction::class)->execute($variant, 1, StockReason::Received);

    Event::assertDispatched(StockAdjusted::class);
});

it('fires the low-stock event when crossing the threshold', function (): void {
    config()->set('shops.inventory.low_stock_threshold', 2);
    Event::fake([StockRanLow::class]);
    $variant = ProductVariant::factory()->create(['stock' => 4, 'reserved' => 0]);

    app(AdjustStockAction::class)->execute($variant, -2, StockReason::Sold);

    Event::assertDispatched(StockRanLow::class, fn (StockRanLow $event): bool => $event->threshold === 2);
});

it('does not fire low-stock for untracked variants', function (): void {
    config()->set('shops.inventory.low_stock_threshold', 100);
    Event::fake([StockRanLow::class]);
    $variant = ProductVariant::factory()->untracked()->create(['stock' => 0]);

    app(AdjustStockAction::class)->execute($variant, -1, StockReason::Sold);

    Event::assertNotDispatched(StockRanLow::class);
});

it('fires the low-stock event only on crossing the threshold, not on every move below it', function (): void {
    config()->set('shops.inventory.low_stock_threshold', 5);
    Event::fake([StockRanLow::class]);
    $variant = ProductVariant::factory()->create(['stock' => 3, 'reserved' => 0]);

    // Already low: moving around below the threshold is not news.
    app(AdjustStockAction::class)->execute($variant, 1, StockReason::Received);
    app(AdjustStockAction::class)->execute($variant, 1, StockReason::Received);
    app(AdjustStockAction::class)->execute($variant, -1, StockReason::Sold);

    Event::assertNotDispatched(StockRanLow::class);

    // Restocked above it, then sold back down across it: one event.
    app(AdjustStockAction::class)->execute($variant, 10, StockReason::Received);
    app(AdjustStockAction::class)->execute($variant, -9, StockReason::Sold);
    app(AdjustStockAction::class)->execute($variant, -1, StockReason::Sold);

    Event::assertDispatchedTimes(StockRanLow::class, 1);
});

it('fires the low-stock event when a reservation crosses the threshold', function (): void {
    config()->set('shops.inventory.low_stock_threshold', 2);
    Event::fake([StockRanLow::class]);
    $variant = ProductVariant::factory()->create(['stock' => 4, 'reserved' => 0]);

    app(AdjustStockAction::class)->execute($variant, 3, StockReason::Reserved);

    Event::assertDispatchedTimes(StockRanLow::class, 1);
});
