<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Inventory\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Shops\Inventory\StockAdjustment;
use RoundlyConsulting\Shops\Products\ProductVariant;

final class StockAdjusted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly ProductVariant $variant,
        public readonly StockAdjustment $adjustment,
    ) {}
}
