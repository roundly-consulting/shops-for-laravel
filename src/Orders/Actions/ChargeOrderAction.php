<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Actions;

use RoundlyConsulting\Shops\Contracts\PaymentGateway;
use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Payments\PaymentResult;

/**
 * Charges an order through the configured payment gateway. On success the order
 * is transitioned to Paid (moving through InProgress first when it is still New);
 * a failed charge leaves the status unchanged.
 */
final class ChargeOrderAction
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly TransitionOrderStatusAction $transition,
    ) {}

    public function execute(Order $order): PaymentResult
    {
        $result = $this->gateway->charge($order);

        if (! $result->successful) {
            return $result;
        }

        if ($order->status === Status::New) {
            $this->transition->execute($order, Status::InProgress);
        }

        $this->transition->execute($order, Status::Paid);

        return $result;
    }
}
