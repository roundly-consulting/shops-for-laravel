<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Actions\Cart;

use RoundlyConsulting\Shops\Cart\Cart;

/**
 * Empties a cart: every line is removed, the cart itself (owner, token, coupon code) stays.
 * Checkout runs it once the order is placed.
 */
final class ClearCartAction
{
    public function execute(Cart $cart): Cart
    {
        $cart->items()->delete();

        return $cart->refresh();
    }
}
