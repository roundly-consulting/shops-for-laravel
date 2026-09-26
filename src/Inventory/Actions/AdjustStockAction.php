<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Inventory\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Shops\Exceptions\InvalidQuantityException;
use RoundlyConsulting\Shops\Inventory\Enums\StockReason;
use RoundlyConsulting\Shops\Inventory\Events\StockAdjusted;
use RoundlyConsulting\Shops\Inventory\Events\StockRanLow;
use RoundlyConsulting\Shops\Inventory\Exceptions\InsufficientStockException;
use RoundlyConsulting\Shops\Inventory\StockAdjustment;
use RoundlyConsulting\Shops\Products\ProductVariant;

/**
 * Applies a signed stock delta to a variant inside a row-locked transaction,
 * writing an auditable ledger row. The sign must match the reason
 * ({@see StockReason::direction()}: received/returned/reserved add, sold/released remove,
 * manual either way) and a zero delta is refused — both throw
 * {@see InvalidQuantityException} before anything is written. A negative delta that would breach available
 * stock on a tracked variant throws {@see InsufficientStockException}; untracked
 * variants never throw and only record the ledger row.
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

            $this->applyDelta($locked, $delta, $reason);

            $adjustment = $this->record($locked, $delta, $reason, $reference, $note);

            $variant->setRawAttributes($locked->getAttributes());

            StockAdjusted::dispatch($variant, $adjustment);

            $this->notifyIfLow($variant);

            return $adjustment;
        });
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

    private function notifyIfLow(ProductVariant $variant): void
    {
        if (! $variant->track_stock) {
            return;
        }

        $threshold = (int) config('shops.inventory.low_stock_threshold', 0);

        if ($variant->availableStock() <= $threshold) {
            StockRanLow::dispatch($variant, $threshold);
        }
    }
}
