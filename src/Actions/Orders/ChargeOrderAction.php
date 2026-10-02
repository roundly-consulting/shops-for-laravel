<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Actions\Orders;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Credits\Interfaces\Creditable;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\Shops\Contracts\PaymentGateway;
use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Orders\Exceptions\IllegalStatusTransitionException;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Payments\Exceptions\DeclinedCharge;
use RoundlyConsulting\Shops\Payments\PaymentResult;
use RoundlyConsulting\Shops\Payments\StoreCreditTender;
use Throwable;

/**
 * Charges an order through the configured payment gateway.
 *
 * The whole charge runs in one transaction under the order's row lock, and the order is
 * re-read from that lock before anything moves: only a New or InProgress order is chargeable,
 * so a Paid, Fulfilled, Canceled or Refunded order — including a second, double-clicked
 * charge or one made from a stale copy — throws {@see IllegalStatusTransitionException} with
 * no store credit debited and the gateway never called.
 *
 * When store-credit tender is enabled (`shops.payment.allow_store_credit`) and the order has a
 * creditable buyer, available store credit is applied first and only the remainder
 * ({@see Order::gatewayAmount()}) is charged. A zero balance — store credit covered it all, or
 * the order is free — skips the gateway and succeeds with a zero amount. On success the order
 * moves to Paid (through InProgress when it is still New) before the lock is released. A
 * declined charge rolls the transaction back: the store credit it applied is returned and the
 * status is unchanged.
 */
final class ChargeOrderAction
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly TransitionOrderStatusAction $transition,
        private readonly StoreCreditTender $storeCredit,
    ) {}

    /**
     * @throws IllegalStatusTransitionException when the order is not New or InProgress.
     */
    public function execute(Order $order): PaymentResult
    {
        $attributes = $order->getAttributes();
        $original = $order->getRawOriginal();
        $relations = $order->getRelations();
        $settled = false;

        try {
            return $order->getConnection()->transaction(function () use ($order, &$settled): PaymentResult {
                $status = $this->lockChargeable($order);

                $this->applyStoreCredit($order);

                $due = $order->gatewayAmount();

                $result = $due->isZero()
                    ? PaymentResult::success($due)
                    : $this->gateway->charge($order);

                if (! $result->successful) {
                    // Roll back the store credit this attempt applied: nothing was paid.
                    throw new DeclinedCharge($result);
                }

                if ($status === Status::New) {
                    $this->transition->execute($order, Status::InProgress);
                }

                $this->transition->execute($order, Status::Paid);

                $settled = true;

                return $result;
            });
        } catch (Throwable $e) {
            if ($settled) {
                // The charge committed; a listener of the after-commit events threw.
                throw $e;
            }

            // The rollback undid every write; the in-memory order goes back to what it was.
            $order->setRawAttributes($original, true);
            $order->setRawAttributes($attributes);
            $order->setRelations($relations);

            if ($e instanceof DeclinedCharge) {
                return $e->result;
            }

            throw $e;
        }
    }

    /**
     * Lock the order row and load the order as stored, so the status checked — and the price
     * and store credit charged — are the current ones, never a stale in-memory copy.
     *
     * @throws IllegalStatusTransitionException when the stored status cannot move to Paid.
     */
    private function lockChargeable(Order $order): Status
    {
        if ($order->exists) {
            $stored = $order->newQueryWithoutScopes()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->first();

            if ($stored instanceof Order) {
                $order->setRawAttributes($stored->getAttributes(), true);
                $order->setRelations([]);
            }
        }

        $status = $order->status;

        if (! in_array($status, [Status::New, Status::InProgress], true)) {
            throw IllegalStatusTransitionException::between($status, Status::Paid);
        }

        return $status;
    }

    private function applyStoreCredit(Order $order): void
    {
        if (! Config::boolean('shops.payment.allow_store_credit')) {
            return;
        }

        if ($order->store_credit_applied !== null) {
            return;
        }

        $customer = $order->customer;

        if ($customer instanceof Model && $customer instanceof Creditable) {
            $this->storeCredit->apply($order, $customer);
        }
    }
}
