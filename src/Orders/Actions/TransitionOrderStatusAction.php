<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Actions;

use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Orders\Events\OrderCanceled;
use RoundlyConsulting\Shops\Orders\Events\OrderFulfilled;
use RoundlyConsulting\Shops\Orders\Events\OrderPaid;
use RoundlyConsulting\Shops\Orders\Events\OrderRefunded;
use RoundlyConsulting\Shops\Orders\Events\OrderStatusChanged;
use RoundlyConsulting\Shops\Orders\Exceptions\IllegalStatusTransitionException;
use RoundlyConsulting\Shops\Orders\Order;

/**
 * Moves an order from its current status to a new one, enforcing the allowed
 * transition map, stamping the matching timestamp column, persisting the
 * change, and firing the generic and status-specific events.
 */
final class TransitionOrderStatusAction
{
    public function __construct(
        private readonly ReleaseStockAction $releaseStock,
    ) {}

    public function execute(Order $order, Status $to): Order
    {
        $from = $order->status;

        if (! $from->canTransitionTo($to)) {
            throw IllegalStatusTransitionException::between($from, $to);
        }

        $order->status = $to;

        $column = $to->timestampColumn();

        if ($column !== null) {
            $order->setAttribute($column, now());
        }

        $order->save();

        $this->settleStock($order, $to);

        OrderStatusChanged::dispatch($order, $from, $to);

        $this->dispatchSpecificEvent($order, $to);

        return $order;
    }

    /**
     * Canceling releases held stock back to availability; fulfilling converts
     * the reservation into a real sale (decrementing on-hand stock).
     */
    private function settleStock(Order $order, Status $to): void
    {
        match ($to) {
            Status::Canceled => $this->releaseStock->execute($order, sell: false),
            Status::Fulfilled => $this->releaseStock->execute($order, sell: true),
            default => null,
        };
    }

    private function dispatchSpecificEvent(Order $order, Status $to): void
    {
        match ($to) {
            Status::Paid => OrderPaid::dispatch($order),
            Status::Fulfilled => OrderFulfilled::dispatch($order),
            Status::Canceled => OrderCanceled::dispatch($order),
            Status::Refunded => OrderRefunded::dispatch($order),
            Status::New, Status::InProgress => null,
        };
    }
}
