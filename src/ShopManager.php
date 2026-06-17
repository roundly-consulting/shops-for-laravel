<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops;

use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Contracts\Coupon;
use RoundlyConsulting\Shops\Orders\Actions\ChargeOrderAction;
use RoundlyConsulting\Shops\Orders\Actions\PlaceOrderAction;
use RoundlyConsulting\Shops\Orders\Actions\TransitionOrderStatusAction;
use RoundlyConsulting\Shops\Orders\Actions\UseCoupon;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\PlaceOrderData;
use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Payments\PaymentResult;
use RoundlyConsulting\Shops\Support\Money\Money;

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
        private readonly UseCoupon $useCoupon,
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

    public function useCoupon(Coupon $coupon, Money $money): Money
    {
        return $this->useCoupon->execute($coupon, $money);
    }
}
