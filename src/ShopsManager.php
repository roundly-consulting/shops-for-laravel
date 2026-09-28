<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Shops\Actions\Cart\AddToCartAction;
use RoundlyConsulting\Shops\Actions\Cart\ClearCartAction;
use RoundlyConsulting\Shops\Actions\Cart\RemoveFromCartAction;
use RoundlyConsulting\Shops\Actions\Cart\UpdateCartItemAction;
use RoundlyConsulting\Shops\Actions\Inventory\AdjustStockAction;
use RoundlyConsulting\Shops\Actions\Orders\ChargeOrderAction;
use RoundlyConsulting\Shops\Actions\Orders\PlaceOrderAction;
use RoundlyConsulting\Shops\Actions\Orders\TransitionOrderStatusAction;
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Cart\CartItem;
use RoundlyConsulting\Shops\Handles\CartHandle;
use RoundlyConsulting\Shops\Handles\CouponsHandle;
use RoundlyConsulting\Shops\Handles\InventoryHandle;
use RoundlyConsulting\Shops\Handles\OrderHandle;
use RoundlyConsulting\Shops\Inventory\Enums\StockReason;
use RoundlyConsulting\Shops\Inventory\StockAdjustment;
use RoundlyConsulting\Shops\Orders\AddressBook;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\PlaceOrderData;
use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Payments\PaymentResult;
use RoundlyConsulting\Shops\Products\ProductVariant;
use RoundlyConsulting\Shops\Shops\CurrentShop;

/**
 * The shops API in one injectable: the `Shops` facade's root, resolved as a singleton.
 *
 * Every area is a scoped handle or sub-accessor — `cart($cart)`, `order($order)`,
 * `inventory($variant)`, `coupons()`, `current()`, `addresses()`. Each state change a handle
 * (or a model convenience method such as `$cart->add()` / `$order->markPaid()`) makes funnels
 * through one of the `@internal` operation methods below, which resolve the action from the
 * container per call — so host overrides and provider fakes bound after boot apply, and
 * `Shops::fake()` records the call.
 */
class ShopsManager
{
    public function __construct(
        protected readonly Container $container,
    ) {}

    /**
     * One cart: add, update and remove lines, clear it, price it and check it out.
     */
    public function cart(Cart $cart): CartHandle
    {
        return new CartHandle($this, $cart);
    }

    /**
     * One order: move it through its statuses, charge it and quote its shipping.
     */
    public function order(Order $order): OrderHandle
    {
        return new OrderHandle($this, $this->container, $order);
    }

    /**
     * One variant's stock: receive deliveries, book returns, correct it and read what is
     * available.
     */
    public function inventory(ProductVariant $variant): InventoryHandle
    {
        return new InventoryHandle($this, $variant);
    }

    /**
     * Coupon previews against a goods subtotal, through the bound DiscountResolver.
     */
    public function coupons(): CouponsHandle
    {
        return new CouponsHandle($this->container);
    }

    /**
     * The shop the current request, job or scoped block belongs to (multi-shop tenancy).
     */
    public function current(): CurrentShop
    {
        return $this->container->make(CurrentShop::class);
    }

    /**
     * A customer's saved addresses, mapped to order address snapshots.
     */
    public function addresses(): AddressBook
    {
        return $this->container->make(AddressBook::class);
    }

    /**
     * @internal the operation behind `Shops::cart($cart)->add()` and `$cart->add()`.
     */
    public function addToCart(Cart $cart, ProductVariant $variant, int $quantity): CartItem
    {
        return $this->container->make(AddToCartAction::class)->execute($cart, $variant, $quantity);
    }

    /**
     * @internal the operation behind `Shops::cart($cart)->update()`.
     */
    public function updateCartItem(Cart $cart, CartItem $item, int $quantity): ?CartItem
    {
        return $this->container->make(UpdateCartItemAction::class)->execute($cart, $item, $quantity);
    }

    /**
     * @internal the operation behind `Shops::cart($cart)->remove()`.
     */
    public function removeFromCart(Cart $cart, CartItem $item): void
    {
        $this->container->make(RemoveFromCartAction::class)->execute($cart, $item);
    }

    /**
     * @internal the operation behind `Shops::cart($cart)->clear()`.
     */
    public function clearCart(Cart $cart): Cart
    {
        return $this->container->make(ClearCartAction::class)->execute($cart);
    }

    /**
     * @internal the operation behind `Shops::cart($cart)->checkout()`.
     */
    public function placeOrder(Cart $cart, PlaceOrderData $data): Order
    {
        return $this->container->make(PlaceOrderAction::class)->execute($cart, $data);
    }

    /**
     * @internal the operation behind `Shops::order($order)->transition()/cancel()/refund()/fulfil()`
     * and the Order model's `transitionTo()` / `mark*()` / `cancel()` / `refund()`.
     */
    public function transition(Order $order, Status $to): Order
    {
        return $this->container->make(TransitionOrderStatusAction::class)->execute($order, $to);
    }

    /**
     * @internal the operation behind `Shops::order($order)->charge()`.
     */
    public function charge(Order $order): PaymentResult
    {
        return $this->container->make(ChargeOrderAction::class)->execute($order);
    }

    /**
     * @internal the operation behind `Shops::inventory($variant)->receive()/returned()/adjust()`.
     */
    public function adjustStock(
        ProductVariant $variant,
        int $delta,
        StockReason $reason,
        ?Model $reference = null,
        ?string $note = null,
    ): StockAdjustment {
        return $this->container->make(AdjustStockAction::class)->execute($variant, $delta, $reason, $reference, $note);
    }
}
