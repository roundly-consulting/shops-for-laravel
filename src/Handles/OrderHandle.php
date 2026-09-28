<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Handles;

use Illuminate\Contracts\Container\Container;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Actions\Orders\QuoteShippingAction;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Address;
use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Orders\Exceptions\IllegalStatusTransitionException;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Payments\PaymentResult;
use RoundlyConsulting\Shops\ShopsManager;

/**
 * One order, returned by `Shops::order($order)`: move it through its statuses, charge it and
 * quote its shipping. Every transition is checked against the status map under the order's
 * row lock and throws {@see IllegalStatusTransitionException} when the move is not allowed.
 */
final readonly class OrderHandle
{
    public function __construct(
        private ShopsManager $manager,
        private Container $container,
        private Order $order,
    ) {}

    /**
     * @throws IllegalStatusTransitionException
     */
    public function transition(Status $to): Order
    {
        return $this->manager->transition($this->order, $to);
    }

    /**
     * Cancel the order; its reserved stock returns to availability.
     *
     * @throws IllegalStatusTransitionException
     */
    public function cancel(): Order
    {
        return $this->transition(Status::Canceled);
    }

    /**
     * Mark the order refunded (and, when configured, grant its total back as store credit).
     *
     * @throws IllegalStatusTransitionException
     */
    public function refund(): Order
    {
        return $this->transition(Status::Refunded);
    }

    /**
     * Mark the order fulfilled; its reservation becomes a sale (on-hand stock decrements).
     *
     * @throws IllegalStatusTransitionException
     */
    public function fulfil(): Order
    {
        return $this->transition(Status::Fulfilled);
    }

    /**
     * Charge the order: store credit first (when enabled), the rest through the payment
     * gateway. A successful charge moves the order to Paid; a failed one leaves it as is.
     */
    public function charge(): PaymentResult
    {
        return $this->manager->charge($this->order);
    }

    /**
     * What shipping the order to the destination costs, through the bound ShippingMethod.
     */
    public function quoteShipping(Address $to): Money
    {
        return $this->container->make(QuoteShippingAction::class)->execute($this->order, $to);
    }
}
