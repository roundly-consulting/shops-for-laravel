<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Inventory\Events;

use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Shops\Inventory\StockAdjustment;
use RoundlyConsulting\Shops\Products\ProductVariant;

final class StockAdjusted
{
    use Dispatchable;

    public function __construct(
        public readonly ProductVariant $variant,
        public readonly StockAdjustment $adjustment,
    ) {}
}
