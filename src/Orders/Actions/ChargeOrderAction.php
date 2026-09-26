<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Credits\Interfaces\Creditable;
use RoundlyConsulting\Shops\Contracts\PaymentGateway;
use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Payments\PaymentResult;
use RoundlyConsulting\Shops\Payments\StoreCreditTender;

/**
 * Charges an order through the configured payment gateway. When store-credit
 * tender is enabled (`shops.payment.allow_store_credit`) and the order has a
 * creditable buyer, available store credit is applied first and only the
 * remainder ({@see Order::gatewayAmount()}) is charged. A zero balance — store
 * credit covered it all, or the order is free — skips the gateway and succeeds
 * with a zero amount. On success the order is transitioned to Paid (moving
 * through InProgress first when it is still New); a failed charge leaves the
 * status unchanged.
 */
final class ChargeOrderAction
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly TransitionOrderStatusAction $transition,
        private readonly StoreCreditTender $storeCredit,
    ) {}

    public function execute(Order $order): PaymentResult
    {
        $this->applyStoreCredit($order);

        $due = $order->gatewayAmount();

        $result = $due->isZero()
            ? PaymentResult::success($due)
            : $this->gateway->charge($order);

        if (! $result->successful) {
            return $result;
        }

        if ($order->status === Status::New) {
            $this->transition->execute($order, Status::InProgress);
        }

        $this->transition->execute($order, Status::Paid);

        return $result;
    }

    private function applyStoreCredit(Order $order): void
    {
        if (! (bool) config('shops.payment.allow_store_credit', false)) {
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
