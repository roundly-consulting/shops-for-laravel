<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Coupons\CouponManager;
use RoundlyConsulting\Coupons\Exceptions\CouponNotRedeemable;
use RoundlyConsulting\Shops\Cart\Actions\ClearCart;
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Contracts\DiscountResolver;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\PlaceOrderData;
use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Orders\Events\OrderPlaced;
use RoundlyConsulting\Shops\Orders\Order;

/**
 * Converts a cart into a placed order in a single transaction: the order takes the
 * cart's currency (a snapshot — config changes never re-denominate it), each cart line is
 * snapshotted into an order item, stock is reserved, the buyer (cart owner or an
 * explicit customer) is linked, an optional coupon is redeemed via
 * coupons-for-laravel and its discount snapshotted onto the order, the
 * billing/shipping addresses are stored, the order number is generated, the cart
 * is cleared, and {@see OrderPlaced} is fired. Any oversell rolls the whole thing
 * back and leaves the cart intact.
 */
final class PlaceOrderAction
{
    public function __construct(
        private readonly AddOrderItemAction $addItem,
        private readonly ReserveStockAction $reserveStock,
        private readonly ClearCart $clearCart,
        private readonly CouponManager $coupons,
        private readonly DiscountResolver $discounts,
    ) {}

    public function execute(Cart $cart, PlaceOrderData $data = new PlaceOrderData): Order
    {
        return $cart->getConnection()->transaction(function () use ($cart, $data): Order {
            $order = new Order([
                'status' => Status::New,
                'currency' => $cart->currency,
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

            // Refresh first so listeners read the snapshotted discount, not a price
            // cached before the coupon was applied.
            $order->refresh();

            OrderPlaced::dispatch($order);

            return $order;
        });
    }

    /**
     * Link and redeem the coupon for the order's goods subtotal, snapshotting the
     * discount it grants onto the order. The discount is resolved *before* redemption,
     * while the coupon is still redeemable — a single-use coupon is exhausted by this
     * very redemption, which runs under the coupon's row lock and has the final say: a
     * coupon it refuses (a racing checkout took the last use) is skipped like any other
     * non-redeemable one. A missing or non-redeemable coupon is silently skipped so it
     * never blocks checkout.
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

        $price = $order->price->getSubtotal();
        $redeemer = $order->customer instanceof Model ? $order->customer : null;

        if (! $coupon->isRedeemableBy($redeemer, $price)) {
            return;
        }

        $discount = $this->discounts->resolve($code, $price);

        try {
            $coupon->redeemBy($redeemer, $price);
        } catch (CouponNotRedeemable) {
            // The check above ran without the coupon's lock: a racing checkout may have used
            // the last redemption since. The locked redemption is the arbiter — skip the
            // coupon (its savepoint rolled back) rather than fail the whole checkout.
            return;
        }

        $order->coupon()->associate($coupon);
        $order->discount = $discount->discount;
        $order->free_shipping = $discount->freeShipping;
        $order->coupon_code = $coupon->code;
        $order->save();
    }
}
