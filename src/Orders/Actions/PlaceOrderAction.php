<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Shops\Cart\Actions\ClearCart;
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\PlaceOrderData;
use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Orders\Events\OrderPlaced;
use RoundlyConsulting\Shops\Orders\Order;

/**
 * Converts a cart into a placed order in a single transaction: each cart line is
 * snapshotted into an order item, stock is reserved, an optional coupon is
 * linked, the billing/shipping addresses are stored, the order number is
 * generated, the cart is cleared, and {@see OrderPlaced} is fired. Any oversell
 * rolls the whole thing back and leaves the cart intact.
 */
final class PlaceOrderAction
{
    public function __construct(
        private readonly AddOrderItemAction $addItem,
        private readonly ReserveStockAction $reserveStock,
        private readonly ClearCart $clearCart,
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

            $this->linkCoupon($order, $data->couponCode ?? $cart->coupon_code);

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

            $this->clearCart->execute($cart);

            OrderPlaced::dispatch($order);

            return $order->refresh();
        });
    }

    private function linkCoupon(Order $order, ?string $code): void
    {
        if ($code === null) {
            return;
        }

        /** @var class-string<Model>|null $model */
        $model = config('shops.discounts.coupon_model') ?? config('shops.orders.coupon_model');

        if ($model === null) {
            return;
        }

        $instance = new $model;
        $couponId = $instance->newQuery()->getQuery()
            ->where('code', $code)
            ->value($instance->getKeyName());

        if ($couponId !== null) {
            $order->coupon_id = (int) $couponId;
        }
    }
}
