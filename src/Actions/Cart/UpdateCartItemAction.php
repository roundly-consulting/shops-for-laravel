<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Actions\Cart;

use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Cart\CartItem;
use RoundlyConsulting\Shops\Exceptions\ForeignItemException;
use RoundlyConsulting\Shops\Exceptions\InvalidQuantityException;
use RoundlyConsulting\Shops\Support\Quantity;

/**
 * Sets a cart line's quantity. Zero removes the line (and returns null); a negative or
 * out-of-range quantity is refused with {@see InvalidQuantityException}. A line of another
 * cart is refused with {@see ForeignItemException} before anything is written.
 */
final class UpdateCartItemAction
{
    /**
     * @throws ForeignItemException when the line belongs to another cart.
     * @throws InvalidQuantityException when the quantity is below 0 or above Quantity::MAX.
     */
    public function execute(Cart $cart, CartItem $item, int $quantity): ?CartItem
    {
        ForeignItemException::assertInCart($cart, $item);

        if ($quantity === 0) {
            $item->delete();
            $cart->unsetRelation('items');

            return null;
        }

        $item->update(['quantity' => Quantity::assertValid($quantity)]);
        $cart->unsetRelation('items');

        return $item;
    }
}
