<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Cart\Actions;

use RoundlyConsulting\Shops\Cart\CartItem;

/**
 * Sets a cart item's quantity, removing it when the quantity drops to zero or
 * below.
 */
final class UpdateCartItem
{
    public function execute(CartItem $item, int $quantity): ?CartItem
    {
        if ($quantity <= 0) {
            $item->delete();

            return null;
        }

        $item->update(['quantity' => $quantity]);

        return $item;
    }
}
