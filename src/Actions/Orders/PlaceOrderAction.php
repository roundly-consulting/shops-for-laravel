<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Actions\Orders;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Coupons\CouponManager;
use RoundlyConsulting\Coupons\Exceptions\CouponNotRedeemable;
use RoundlyConsulting\Shops\Actions\Cart\ClearCartAction;
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Contracts\DiscountResolver;
use RoundlyConsulting\Shops\Inventory\Exceptions\InsufficientStockException;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\PlaceOrderData;
use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Orders\Events\OrderPlaced;
use RoundlyConsulting\Shops\Orders\Exceptions\CheckoutRefusedException;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Products\ProductVariant;

/**
 * Converts a cart into a placed order in a single transaction: the order takes the
 * cart's currency (a snapshot — config changes never re-denominate it), each cart line becomes
 * an order item at the name, sku, price and tax class the cart snapshotted (what the customer
 * saw, never a later catalog price), stock is reserved, the buyer (cart owner or an
 * explicit customer) is linked, an optional coupon is redeemed via
 * coupons-for-laravel and its discount snapshotted onto the order, the
 * billing/shipping addresses are stored, the order number is generated, the cart
 * is cleared, and {@see OrderPlaced} is fired (after commit). Any oversell rolls the whole
 * thing back and leaves the cart intact.
 *
 * The cart row is locked for the whole checkout and its lines are read under that lock, so a
 * double-submitted checkout places one order: the second finds the cart empty and is refused.
 * A cart with no lines, or with a line whose variant was deleted or soft-deleted since it was
 * added, is refused with {@see CheckoutRefusedException} before anything is written.
 */
final class PlaceOrderAction
{
    public function __construct(
        private readonly AddOrderItemAction $addItem,
        private readonly ReserveStockAction $reserveStock,
        private readonly ClearCartAction $clearCart,
        private readonly CouponManager $coupons,
        private readonly DiscountResolver $discounts,
    ) {}

    /**
     * @throws CheckoutRefusedException when the cart is empty (also a second, double-submitted
     *                                  checkout) or a line's variant was (soft-)deleted.
     * @throws InsufficientStockException when any line would oversell.
     */
    public function execute(Cart $cart, PlaceOrderData $data = new PlaceOrderData): Order
    {
        return $cart->getConnection()->transaction(function () use ($cart, $data): Order {
            // One checkout per cart at a time: a double-submitted second checkout waits here,
            // then finds the cart the first one cleared — and is refused as empty.
            $locked = $this->lockCart($cart);

            $lines = $cart->items()->with('variant')->get();

            if ($lines->isEmpty()) {
                throw CheckoutRefusedException::emptyCart($cart);
            }

            foreach ($lines as $line) {
                if ($line->variant === null) {
                    throw CheckoutRefusedException::lineUnavailable($line);
                }
            }

            $order = new Order([
                'status' => Status::New,
                'currency' => $locked->currency,
                'billing_address' => $data->billing,
                'shipping_address' => $data->shipping,
                'note' => $data->note,
            ]);

            $customer = $data->customer ?? $locked->owner;

            if ($customer instanceof Model) {
                $order->customer()->associate($customer);
            }

            if ($locked->shop_id !== null) {
                $order->shop_id = $locked->shop_id;
            }

            $order->save();

            foreach ($lines as $line) {
                /** @var ProductVariant $variant checked above */
                $variant = $line->variant;

                // The line's own snapshot — the price the customer saw — not today's catalog.
                $this->addItem->execute($order, $variant, $line->quantity, $line);
            }

            $this->reserveStock->execute($order);

            $this->redeemCoupon($order, $data->couponCode ?? $locked->coupon_code);

            $this->clearCart->execute($cart);

            // Refresh first so listeners read the snapshotted discount, not a price
            // cached before the coupon was applied.
            $order->refresh();

            OrderPlaced::dispatch($order);

            return $order;
        });
    }

    /**
     * Lock the cart row and read it as stored — its currency, owner, shop and coupon code are
     * taken from the locked row, never a stale copy.
     */
    private function lockCart(Cart $cart): Cart
    {
        $locked = $cart->newQueryWithoutScopes()
            ->whereKey($cart->getKey())
            ->lockForUpdate()
            ->first();

        return $locked instanceof Cart ? $locked : $cart;
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
