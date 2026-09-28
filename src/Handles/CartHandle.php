<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Handles;

use RoundlyConsulting\Money\Exceptions\CurrencyMismatch;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Cart\CartItem;
use RoundlyConsulting\Shops\Exceptions\ForeignItemException;
use RoundlyConsulting\Shops\Exceptions\InvalidQuantityException;
use RoundlyConsulting\Shops\Inventory\Exceptions\InsufficientStockException;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\PlaceOrderData;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Price;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Products\ProductVariant;
use RoundlyConsulting\Shops\ShopsManager;

/**
 * One cart, returned by `Shops::cart($cart)`. Lines of another cart are refused with
 * {@see ForeignItemException}.
 */
final readonly class CartHandle
{
    public function __construct(
        private ShopsManager $manager,
        private Cart $cart,
    ) {}

    /**
     * Add a variant, snapshotting its price; a variant already in the cart increments its line.
     *
     * @throws InvalidQuantityException when the quantity (or the merged line) is not 1..Quantity::MAX.
     * @throws CurrencyMismatch when the variant is priced in another currency than the cart.
     */
    public function add(ProductVariant $variant, int $quantity = 1): CartItem
    {
        return $this->manager->addToCart($this->cart, $variant, $quantity);
    }

    /**
     * Set a line's quantity; 0 removes the line and returns null.
     *
     * @throws ForeignItemException when the line belongs to another cart.
     * @throws InvalidQuantityException when the quantity is below 0 or above Quantity::MAX.
     */
    public function update(CartItem $item, int $quantity): ?CartItem
    {
        return $this->manager->updateCartItem($this->cart, $item, $quantity);
    }

    /**
     * @throws ForeignItemException when the line belongs to another cart.
     */
    public function remove(CartItem $item): void
    {
        $this->manager->removeFromCart($this->cart, $item);
    }

    /**
     * Remove every line; the cart itself stays.
     */
    public function clear(): Cart
    {
        return $this->manager->clearCart($this->cart);
    }

    /**
     * The cart's price — subtotal, discount, tax, total. The coupon defaults to the cart's
     * stored `coupon_code`; pass one to preview another. Never records a redemption.
     */
    public function price(?string $couponCode = null): Price
    {
        return $this->cart->price($couponCode);
    }

    /**
     * The goods subtotal before any discount.
     */
    public function subtotal(): Money
    {
        return $this->cart->subtotal();
    }

    /**
     * Turn the cart into a placed order: lines snapshotted, stock reserved, coupon redeemed,
     * cart cleared, OrderPlaced fired — all in one transaction.
     *
     * @throws InsufficientStockException when any line would oversell; nothing is written.
     */
    public function checkout(PlaceOrderData $data = new PlaceOrderData): Order
    {
        return $this->manager->placeOrder($this->cart, $data);
    }
}
