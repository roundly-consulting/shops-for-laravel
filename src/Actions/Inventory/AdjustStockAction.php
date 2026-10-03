<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Actions\Inventory;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Shops\Exceptions\InvalidQuantityException;
use RoundlyConsulting\Shops\Inventory\Enums\StockReason;
use RoundlyConsulting\Shops\Inventory\Events\StockAdjusted;
use RoundlyConsulting\Shops\Inventory\Events\StockRanLow;
use RoundlyConsulting\Shops\Inventory\Exceptions\InsufficientStockException;
use RoundlyConsulting\Shops\Inventory\StockAdjustment;
use RoundlyConsulting\Shops\Products\ProductVariant;
use RoundlyConsulting\Shops\Support\ShopsConfig;

/**
 * Applies a signed stock delta to a variant inside a row-locked transaction,
 * writing an auditable ledger row. The sign must match the reason
 * ({@see StockReason::direction()}: received/returned/reserved add, sold/released remove,
 * manual either way) and a zero delta is refused — both throw
 * {@see InvalidQuantityException} before anything is written. A sale, or a reservation, that would
 * take a tracked variant's available stock below zero throws {@see InsufficientStockException} —
 * decided against the row-locked variant, never the caller's copy; untracked variants never
 * throw and only record the ledger row. StockRanLow fires when an adjustment takes available
 * stock across `shops.inventory.low_stock_threshold` (from above it to at or below it).
 */
final class AdjustStockAction
{
    public function execute(
        ProductVariant $variant,
        int $delta,
        StockReason $reason,
        ?Model $reference = null,
        ?string $note = null,
    ): StockAdjustment {
        $this->assertDirection($delta, $reason);

        return $variant->getConnection()->transaction(function () use ($variant, $delta, $reason, $reference, $note): StockAdjustment {
            /** @var ProductVariant $locked */
            $locked = $variant->newQuery()->lockForUpdate()->findOrFail($variant->getKey());

            $availableBefore = $locked->availableStock();

            $this->applyDelta($locked, $delta, $reason);

            $adjustment = $this->record($locked, $delta, $reason, $reference, $note);

            $this->syncStock($variant, $locked);

            StockAdjusted::dispatch($variant, $adjustment);

            $this->notifyIfLow($variant, $availableBefore);

            return $adjustment;
        });
    }

    /**
     * Copy the ledger-managed columns from the locked row onto the caller's variant as CLEAN
     * attributes. Copying them dirty (or copying the whole row) would make a later
     * `$variant->save()` re-write a stock figure another request has since moved on — a lost
     * sale — and would overwrite the caller's own unsaved edits.
     */
    private function syncStock(ProductVariant $variant, ProductVariant $locked): void
    {
        $columns = ['stock', 'reserved', $variant->getUpdatedAtColumn()];
        $attributes = $variant->getAttributes();

        foreach ($columns as $column) {
            $attributes[$column] = $locked->getAttributes()[$column] ?? null;
        }

        $variant->setRawAttributes($attributes);
        $variant->syncOriginalAttributes($columns);
    }

    /**
     * @throws InvalidQuantityException when the delta is zero or its sign contradicts the reason.
     */
    private function assertDirection(int $delta, StockReason $reason): void
    {
        if ($delta === 0) {
            throw InvalidQuantityException::zeroDelta($reason);
        }

        $direction = $reason->direction();

        if ($direction !== 0 && ($delta <=> 0) !== $direction) {
            throw InvalidQuantityException::wrongSign($reason, $delta);
        }
    }

    private function applyDelta(ProductVariant $variant, int $delta, StockReason $reason): void
    {
        if ($reason->affectsReserved()) {
            // A hold is taken against what is available on the LOCKED row — never the caller's
            // copy — so two checkouts can never both reserve the last unit.
            if ($variant->track_stock && $delta > 0 && $variant->availableStock() < $delta) {
                throw InsufficientStockException::for($variant, $delta);
            }

            $variant->reserved = max(0, $variant->reserved + $delta);
            $variant->save();

            return;
        }

        if ($variant->track_stock && $delta < 0 && $variant->availableStock() + $delta < 0) {
            throw InsufficientStockException::for($variant, abs($delta));
        }

        $variant->stock += $delta;
        $variant->save();
    }

    private function record(
        ProductVariant $variant,
        int $delta,
        StockReason $reason,
        ?Model $reference,
        ?string $note,
    ): StockAdjustment {
        $adjustment = new StockAdjustment([
            'quantity' => $delta,
            'reason' => $reason,
            'note' => $note,
        ]);

        $adjustment->variant()->associate($variant);

        if ($reference !== null) {
            $adjustment->reference()->associate($reference);
        }

        $adjustment->save();

        return $adjustment;
    }

    /**
     * Fire StockRanLow when this adjustment took available stock from above the threshold to at
     * or below it — once per crossing, not on every move while the variant is already low.
     */
    private function notifyIfLow(ProductVariant $variant, int $availableBefore): void
    {
        if (! $variant->track_stock) {
            return;
        }

        $threshold = ShopsConfig::lowStockThreshold();

        if ($availableBefore > $threshold && $variant->availableStock() <= $threshold) {
            StockRanLow::dispatch($variant, $threshold);
        }
    }
}
