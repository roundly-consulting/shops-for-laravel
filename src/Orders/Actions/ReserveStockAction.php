<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Actions;

use RoundlyConsulting\Shops\Inventory\Actions\AdjustStockAction;
use RoundlyConsulting\Shops\Inventory\Enums\StockReason;
use RoundlyConsulting\Shops\Inventory\Exceptions\InsufficientStockException;
use RoundlyConsulting\Shops\Orders\Order;

/**
 * Reserves each order line's quantity against its variant's stock. The whole
 * reservation runs in one transaction, so an oversell on any line rolls back
 * every reservation and leaves stock untouched.
 */
final class ReserveStockAction
{
    public function __construct(
        private readonly AdjustStockAction $adjustStock,
    ) {}

    public function execute(Order $order): void
    {
        $order->getConnection()->transaction(function () use ($order): void {
            foreach ($order->items()->with('variant')->get() as $item) {
                $variant = $item->variant;

                if ($variant === null) {
                    continue;
                }

                if (! $variant->inStock($item->quantity)) {
                    throw InsufficientStockException::for($variant, $item->quantity);
                }

                $this->adjustStock->execute(
                    $variant,
                    $item->quantity,
                    StockReason::Reserved,
                    $order,
                );
            }
        });
    }
}
