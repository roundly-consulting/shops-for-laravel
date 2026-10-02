<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Shops\Actions\Inventory\AdjustStockAction;
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Facades\Shops;
use RoundlyConsulting\Shops\Inventory\Enums\StockReason;
use RoundlyConsulting\Shops\Inventory\Exceptions\InsufficientStockException;
use RoundlyConsulting\Shops\Inventory\StockAdjustment;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Products\ProductVariant;

/**
 * Stock is an oversell boundary: the last unit must never be sold twice, and a
 * restock must never be silently dropped. `AdjustStockAction` defends both with a
 * pessimistic compare-and-set — it re-reads the variant under `lockForUpdate()`
 * inside a transaction, guards against that *freshly read* row, and only then
 * writes. The caller's in-memory copy is never trusted.
 *
 * These tests attack exactly that: every one of them hands the action a STALE
 * variant. If the locked re-read were ever dropped — say by mutating the passed
 * instance directly — the guard would read a stale `stock` and the shop would
 * oversell. That is the regression this file exists to catch.
 */
function trackedVariant(int $stock): ProductVariant
{
    return ProductVariant::factory()->create([
        'track_stock' => true,
        'stock' => $stock,
        'reserved' => 0,
    ]);
}

/**
 * The lock must be taken at the TOP of the atomic unit it protects — the same
 * transaction frame that also writes the ledger row and dispatches the events. This
 * pins the depth, not just the presence, of the lock.
 *
 * It is also what rules out the toolkit's `LockedUpdate`: that helper always opens a
 * transaction of its own, so the lock would land one level deeper (in a savepoint
 * that is released before the ledger row is written) than the critical section that
 * depends on it. Swapping it in moves this assertion from `baseline + 1` to
 * `baseline + 2` — the whole rest of the suite stays green, which is precisely why
 * this pin exists.
 *
 * The depth is measured relative to the caller rather than from zero. That is no
 * longer because the suite sits inside `RefreshDatabase`'s transaction — the base case
 * resets a real engine by dropping tables, opening no transaction, so the baseline is
 * 0 today. It stays relative because the datum under test is "one level below whoever
 * called", which is what `LockedUpdate` violated.
 */
it('locks the variant row inside a transaction before the guard decides', function (): void {
    $variant = trackedVariant(5);

    $baseline = DB::transactionLevel();

    $locks = recordLocks(function () use ($variant): void {
        app(AdjustStockAction::class)->execute($variant, -1, StockReason::Sold);
    });

    // Exactly one locked read, on the variant row, taken one transaction level below
    // the caller — i.e. the action opened its own transaction and locked inside it.
    // The guard therefore decides against a locked row, never the caller's copy.
    expect($locks)->toHaveCount(1)
        ->and($locks[0]['marker'])->toBe('lock-for-update')
        ->and($locks[0]['sql'])->toContain('product_variants')
        ->and($locks[0]['transactionDepth'])->toBe($baseline + 1);
});

it('never sells the last unit twice', function (): void {
    $variant = trackedVariant(1);

    // Two racing buyers, each holding the variant as they saw it: one unit available.
    $first = ProductVariant::query()->findOrFail($variant->getKey());
    $second = ProductVariant::query()->findOrFail($variant->getKey());

    app(AdjustStockAction::class)->execute($first, -1, StockReason::Sold);

    // The second buyer's view is now stale. The locked re-read is what catches it.
    expect(fn () => app(AdjustStockAction::class)->execute($second, -1, StockReason::Sold))
        ->toThrow(InsufficientStockException::class);

    expect($variant->refresh()->stock)->toBe(0);
});

it('never reserves the last unit twice', function (): void {
    $variant = trackedVariant(1);

    $first = ProductVariant::query()->findOrFail($variant->getKey());
    $second = ProductVariant::query()->findOrFail($variant->getKey());

    app(AdjustStockAction::class)->execute($first, 1, StockReason::Reserved);

    expect($second->refresh()->availableStock())->toBe(0);

    // A reservation is a hold against available stock; the second buyer cannot take
    // a unit the first already holds.
    expect($second->inStock(1))->toBeFalse();

    expect(fn () => app(AdjustStockAction::class)->execute($second, -1, StockReason::Sold))
        ->toThrow(InsufficientStockException::class);
});

it('does not lose a restock that races another restock', function (): void {
    $variant = trackedVariant(0);

    // Both restocks were loaded when stock was 0. A write that trusted the passed
    // instance would persist `stock = 0 + 5` twice and land on 5, silently dropping
    // one delivery. The locked re-read makes the second see the first.
    $first = ProductVariant::query()->findOrFail($variant->getKey());
    $second = ProductVariant::query()->findOrFail($variant->getKey());

    app(AdjustStockAction::class)->execute($first, 5, StockReason::Received);
    app(AdjustStockAction::class)->execute($second, 5, StockReason::Received);

    expect($variant->refresh()->stock)->toBe(10);
});

it('does not lose a sale that races a restock', function (): void {
    $variant = trackedVariant(10);

    $seller = ProductVariant::query()->findOrFail($variant->getKey());
    $restocker = ProductVariant::query()->findOrFail($variant->getKey());

    app(AdjustStockAction::class)->execute($seller, -4, StockReason::Sold);
    app(AdjustStockAction::class)->execute($restocker, 3, StockReason::Received);

    // 10 - 4 + 3. Neither delta is folded away by the other.
    expect($variant->refresh()->stock)->toBe(9);
});

it('rolls back the whole adjustment when the guard rejects it', function (): void {
    $variant = trackedVariant(1);
    $baseline = DB::transactionLevel();

    expect(fn () => app(AdjustStockAction::class)->execute($variant, -5, StockReason::Sold))
        ->toThrow(InsufficientStockException::class);

    // No ledger row, no stock movement, and the action's transaction is unwound.
    expect($variant->refresh()->stock)->toBe(1)
        ->and(StockAdjustment::query()->count())->toBe(0)
        ->and(DB::transactionLevel())->toBe($baseline);
});

it('lets an untracked variant go negative without ever throwing', function (): void {
    $variant = ProductVariant::factory()->create([
        'track_stock' => false,
        'stock' => 0,
        'reserved' => 0,
    ]);

    app(AdjustStockAction::class)->execute($variant, -3, StockReason::Sold);

    // Untracked stock is a counter, not a boundary — the guard is deliberately off.
    expect($variant->refresh()->stock)->toBe(-3)
        ->and(StockAdjustment::query()->count())->toBe(1);
});

it('never lets a release drive the reserved count below zero', function (): void {
    $variant = trackedVariant(5);

    app(AdjustStockAction::class)->execute($variant, 2, StockReason::Reserved);

    // A double release (a cancel racing a fulfilment) must not manufacture
    // availability by pushing `reserved` negative.
    app(AdjustStockAction::class)->execute($variant, -2, StockReason::Released);
    app(AdjustStockAction::class)->execute($variant, -2, StockReason::Released);

    expect($variant->refresh()->reserved)->toBe(0)
        ->and($variant->availableStock())->toBe(5);
});

it('never reserves beyond available stock, even through the action directly', function (): void {
    $variant = trackedVariant(1);

    expect(fn () => app(AdjustStockAction::class)->execute($variant, 5, StockReason::Reserved))
        ->toThrow(InsufficientStockException::class);

    expect($variant->refresh()->reserved)->toBe(0)
        ->and(StockAdjustment::query()->count())->toBe(0);
});

it('checks a reservation against the locked row, never a stale copy', function (): void {
    $variant = trackedVariant(1);

    $first = ProductVariant::query()->findOrFail($variant->getKey());
    $second = ProductVariant::query()->findOrFail($variant->getKey());

    app(AdjustStockAction::class)->execute($first, 1, StockReason::Reserved);

    // The second buyer still sees one unit available in memory.
    expect($second->availableStock())->toBe(1);

    expect(fn () => app(AdjustStockAction::class)->execute($second, 1, StockReason::Reserved))
        ->toThrow(InsufficientStockException::class);

    expect($variant->refresh()->reserved)->toBe(1);
});

it('lets an untracked variant reserve past its stock', function (): void {
    $variant = ProductVariant::factory()->create(['track_stock' => false, 'stock' => 0, 'reserved' => 0]);

    app(AdjustStockAction::class)->execute($variant, 3, StockReason::Reserved);

    expect($variant->refresh()->reserved)->toBe(3);
});

/**
 * Two checkouts race for the last unit: B runs to completion between A reading its variants
 * and A reserving them. A must lose — the reservation is decided under the variant's row lock.
 */
it('never oversells the last unit to two interleaved checkouts', function (): void {
    $variant = ProductVariant::factory()->withEurPrice('1000')->create(['track_stock' => true, 'stock' => 1, 'reserved' => 0]);

    $cartA = Cart::create(['currency' => 'EUR']);
    $cartA->add($variant);
    $cartB = Cart::create(['currency' => 'EUR']);
    $cartB->add($variant);

    $state = ['seenItems' => false, 'interleaved' => false, 'orderB' => null, 'reservedAfterB' => null];

    DB::listen(function (QueryExecuted $query) use (&$state, $cartB, $variant): void {
        if ($state['interleaved']) {
            return;
        }

        if (str_contains($query->sql, 'from "order_items"')) {
            $state['seenItems'] = true;

            return;
        }

        // A has just read its order's variants (one unit available). Run B now.
        if ($state['seenItems'] && str_contains($query->sql, 'from "product_variants"')) {
            $state['interleaved'] = true;
            $state['orderB'] = Shops::cart($cartB)->checkout();
            $state['reservedAfterB'] = $variant->fresh()?->reserved;
        }
    });

    expect(fn () => Shops::cart($cartA)->checkout())->toThrow(InsufficientStockException::class);

    expect($state['interleaved'])->toBeTrue()
        ->and($state['orderB'])->toBeInstanceOf(Order::class)
        ->and($state['reservedAfterB'])->toBe(1);
});
