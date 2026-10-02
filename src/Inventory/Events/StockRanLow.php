<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Inventory\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Shops\Products\ProductVariant;

/**
 * Fired when an adjustment takes a tracked variant's available stock from above the
 * configured low-stock threshold to at or below it — once per crossing.
 */
final class StockRanLow implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly ProductVariant $variant,
        public readonly int $threshold,
    ) {}
}
