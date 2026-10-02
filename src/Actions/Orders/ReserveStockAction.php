<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Actions\Orders;

use RoundlyConsulting\Shops\Actions\Inventory\AdjustStockAction;
use RoundlyConsulting\Shops\Inventory\Enums\StockReason;
use RoundlyConsulting\Shops\Inventory\Exceptions\InsufficientStockException;
use RoundlyConsulting\Shops\Orders\Order;

/**
 * Reserves each order line's quantity against its variant's stock. Each hold is checked
 * against the variant's row-locked availability ({@see AdjustStockAction}), so concurrent
 * checkouts never oversell. The whole reservation runs in one transaction, so an oversell on
 * any line rolls back every reservation and leaves stock untouched.
 *
 * Building block of checkout ({@see PlaceOrderAction}); not on the facade.
 *
 * @internal
 */
final class ReserveStockAction
{
    public function __construct(
        private readonly AdjustStockAction $adjustStock,
    ) {}

    /**
     * @throws InsufficientStockException when a line would oversell.
     */
    public function execute(Order $order): void
    {
        $order->getConnection()->transaction(function () use ($order): void {
            foreach ($order->items()->with('variant')->get() as $item) {
                $variant = $item->variant;

                if ($variant === null) {
                    continue;
                }

                // Availability is checked by the adjustment, under the variant's row lock.
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
