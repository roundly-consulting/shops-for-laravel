<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Coupons\CouponManager;
use RoundlyConsulting\Shops\Cart\Actions\ClearCart;
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Discounts\MoneyBridge;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\PlaceOrderData;
use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Orders\Events\OrderPlaced;
use RoundlyConsulting\Shops\Orders\Order;

/**
 * Converts a cart into a placed order in a single transaction: each cart line is
 * snapshotted into an order item, stock is reserved, the buyer (cart owner or an
 * explicit customer) is linked, an optional coupon is redeemed via
 * coupons-for-laravel, the billing/shipping addresses are stored, the order
 * number is generated, the cart is cleared, and {@see OrderPlaced} is fired. Any
 * oversell rolls the whole thing back and leaves the cart intact.
 */
final class PlaceOrderAction
{
    public function __construct(
        private readonly AddOrderItemAction $addItem,
        private readonly ReserveStockAction $reserveStock,
        private readonly ClearCart $clearCart,
        private readonly CouponManager $coupons,
        private readonly MoneyBridge $moneyBridge,
    ) {}

    public function execute(Cart $cart, PlaceOrderData $data = new PlaceOrderData): Order
    {
        return $cart->getConnection()->transaction(function () use ($cart, $data): Order {
            $order = new Order([
                'status' => Status::New,
                'billing_address' => $data->billing,
                'shipping_address' => $data->shipping,
                'note' => $data->note,
            ]);

            $customer = $data->customer ?? $cart->owner;

            if ($customer instanceof Model) {
                $order->customer()->associate($customer);
            }

            if ($cart->shop_id !== null) {
                $order->shop_id = $cart->shop_id;
            }

            $order->save();

            foreach ($cart->items()->with('variant')->get() as $cartItem) {
                $variant = $cartItem->variant;

                if ($variant === null) {
                    continue;
                }

                $this->addItem->execute($order, $variant, $cartItem->quantity);
            }

            $this->reserveStock->execute($order);

            $this->redeemCoupon($order, $data->couponCode ?? $cart->coupon_code);

            $this->clearCart->execute($cart);

            OrderPlaced::dispatch($order);

            return $order->refresh();
        });
    }

    /**
     * Link and redeem the coupon for the order's goods subtotal. A missing or
     * non-redeemable coupon is silently skipped so it never blocks checkout.
     */
    private function redeemCoupon(Order $order, ?string $code): void
    {
        if ($code === null || $code === '') {
            return;
        }

        $coupon = $this->coupons->find($code);

        if ($coupon === null) {
            return;
        }

        $price = $this->moneyBridge->toCoupons($order->price->getSubtotal());
        $redeemer = $order->customer instanceof Model ? $order->customer : null;

        if (! $coupon->isRedeemableBy($redeemer, $price)) {
            return;
        }

        $order->coupon()->associate($coupon);
        $order->save();

        $coupon->redeemBy($redeemer, $price);
    }
}
