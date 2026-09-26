<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops;

use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Contracts\DiscountResolver;
use RoundlyConsulting\Shops\Discounts\DiscountResult;
use RoundlyConsulting\Shops\Orders\Actions\ChargeOrderAction;
use RoundlyConsulting\Shops\Orders\Actions\PlaceOrderAction;
use RoundlyConsulting\Shops\Orders\Actions\TransitionOrderStatusAction;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\PlaceOrderData;
use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Payments\PaymentResult;

/**
 * Discoverable entry point fronting the package's actions, resolved as a
 * singleton and exposed through the optional `Shop` facade.
 */
final class ShopManager
{
    public function __construct(
        private readonly PlaceOrderAction $placeOrder,
        private readonly TransitionOrderStatusAction $transition,
        private readonly ChargeOrderAction $charge,
        private readonly DiscountResolver $discounts,
    ) {}

    public function placeOrder(Cart $cart, PlaceOrderData $data = new PlaceOrderData): Order
    {
        return $this->placeOrder->execute($cart, $data);
    }

    public function transition(Order $order, Status $to): Order
    {
        return $this->transition->execute($order, $to);
    }

    public function charge(Order $order): PaymentResult
    {
        return $this->charge->execute($order);
    }

    /**
     * Preview the discount a coupon code applies to a goods subtotal, via the
     * coupons-backed DiscountResolver. Does not record a redemption.
     */
    public function discountFor(string $code, Money $goods): DiscountResult
    {
        return $this->discounts->resolve($code, $goods);
    }
}
