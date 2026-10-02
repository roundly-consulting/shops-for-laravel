<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Actions\Orders;

use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Orders\Events\OrderCanceled;
use RoundlyConsulting\Shops\Orders\Events\OrderFulfilled;
use RoundlyConsulting\Shops\Orders\Events\OrderPaid;
use RoundlyConsulting\Shops\Orders\Events\OrderRefunded;
use RoundlyConsulting\Shops\Orders\Events\OrderStatusChanged;
use RoundlyConsulting\Shops\Orders\Exceptions\IllegalStatusTransitionException;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Payments\StoreCreditTender;
use Throwable;

/**
 * Moves an order from its current status to a new one, enforcing the allowed
 * transition map, stamping the matching timestamp column, persisting the
 * change, and firing the generic and status-specific events. The current status is
 * read under the order's row lock, so two requests can never both make the same move.
 * Canceling also returns any store credit applied to the order to the buyer. A move that fails
 * (an illegal transition, or a fulfilment that cannot sell its stock) changes nothing — in the
 * database or on the in-memory order.
 */
final class TransitionOrderStatusAction
{
    public function __construct(
        private readonly ReleaseStockAction $releaseStock,
        private readonly StoreCreditTender $storeCredit,
    ) {}

    public function execute(Order $order, Status $to): Order
    {
        $attributes = $order->getAttributes();
        $original = $order->getRawOriginal();
        $exists = $order->exists;

        try {
            $from = $this->apply($order, $to);
        } catch (Throwable $e) {
            // The transaction rolled the row back; put the in-memory order back too, so a retry
            // on this instance writes the status instead of seeing it as already clean.
            $order->setRawAttributes($original, true);
            $order->setRawAttributes($attributes);
            $order->exists = $exists;

            throw $e;
        }

        OrderStatusChanged::dispatch($order, $from, $to);

        $this->dispatchSpecificEvent($order, $to);

        return $order;
    }

    private function apply(Order $order, Status $to): Status
    {
        return $order->getConnection()->transaction(function () use ($order, $to): Status {
            $from = $this->currentStatus($order);

            if (! $from->canTransitionTo($to)) {
                throw IllegalStatusTransitionException::between($from, $to);
            }

            $order->status = $to;

            $column = $to->timestampColumn();

            if ($column !== null) {
                $order->setAttribute($column, now());
            }

            $order->save();

            $this->settleStock($order, $from, $to);

            if ($to === Status::Canceled) {
                // A canceled order was never paid: hand back any store credit applied to it.
                $this->storeCredit->restore($order);
            }

            return $from;
        });
    }

    /**
     * The status the order is in *now*, read under its row lock — not the in-memory copy,
     * which may be stale (a second request that loaded the order before the first one moved
     * it on). Deciding on a stale status would, say, refund an order twice.
     */
    private function currentStatus(Order $order): Status
    {
        if (! $order->exists) {
            return $order->status;
        }

        $stored = $order->newQueryWithoutScopes()
            ->whereKey($order->getKey())
            ->lockForUpdate()
            ->first([$order->getKeyName(), 'status']);

        return $stored instanceof Order ? $stored->status : $order->status;
    }

    /**
     * Canceling — and refunding an order that was paid but never fulfilled — releases held
     * stock back to availability; fulfilling converts the reservation into a real sale
     * (decrementing on-hand stock). Refunding a fulfilled order leaves stock alone: the goods
     * shipped, and a return is booked through the inventory handle.
     */
    private function settleStock(Order $order, Status $from, Status $to): void
    {
        match (true) {
            $to === Status::Canceled,
            $to === Status::Refunded && $from === Status::Paid => $this->releaseStock->execute($order, sell: false),
            $to === Status::Fulfilled => $this->releaseStock->execute($order, sell: true),
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
