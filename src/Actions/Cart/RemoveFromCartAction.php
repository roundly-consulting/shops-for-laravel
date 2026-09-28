<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Actions\Cart;

use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Cart\CartItem;
use RoundlyConsulting\Shops\Exceptions\ForeignItemException;

/**
 * Removes one line from a cart. A line of another cart is refused with
 * {@see ForeignItemException} and left untouched.
 */
final class RemoveFromCartAction
{
    /**
     * @throws ForeignItemException when the line belongs to another cart.
     */
    public function execute(Cart $cart, CartItem $item): void
    {
        ForeignItemException::assertInCart($cart, $item);

        $item->delete();

        $cart->unsetRelation('items');
    }
}
