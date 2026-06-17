<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Actions;

use RoundlyConsulting\Shops\Inventory\Actions\AdjustStockAction;
use RoundlyConsulting\Shops\Inventory\Enums\StockReason;
use RoundlyConsulting\Shops\Orders\Order;

/**
 * Releases the stock previously reserved for an order. Used when an order is
 * canceled (`sell: false` — quantity returns to availability) or fulfilled
 * (`sell: true` — the reservation is converted into an actual sale, decrementing
 * on-hand stock).
 */
final class ReleaseStockAction
{
    public function __construct(
        private readonly AdjustStockAction $adjustStock,
    ) {}

    public function execute(Order $order, bool $sell = false): void
    {
        $order->getConnection()->transaction(function () use ($order, $sell): void {
            foreach ($order->items()->with('variant')->get() as $item) {
                $variant = $item->variant;

                if ($variant === null) {
                    continue;
                }

                // Free the held reservation regardless of outcome.
                $this->adjustStock->execute(
                    $variant,
                    -$item->quantity,
                    StockReason::Released,
                    $order,
                );

                if ($sell) {
                    $this->adjustStock->execute(
                        $variant->refresh(),
                        -$item->quantity,
                        StockReason::Sold,
                        $order,
                    );
                }
            }
        });
    }
}
