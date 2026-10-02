<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Inventory\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Shops\Products\ProductVariant;

/**
 * Fired when a tracked variant's available stock drops to or below the
 * configured low-stock threshold.
 */
final class StockRanLow implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly ProductVariant $variant,
        public readonly int $threshold,
    ) {}
}
