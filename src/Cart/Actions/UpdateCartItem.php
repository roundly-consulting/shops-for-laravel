<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Cart\Actions;

use RoundlyConsulting\Shops\Cart\CartItem;
use RoundlyConsulting\Shops\Exceptions\InvalidQuantityException;
use RoundlyConsulting\Shops\Support\Quantity;

/**
 * Sets a cart item's quantity. Zero removes the line (and returns null); a negative or
 * out-of-range quantity is refused with {@see InvalidQuantityException}.
 */
final class UpdateCartItem
{
    /**
     * @throws InvalidQuantityException when the quantity is below 0 or above Quantity::MAX.
     */
    public function execute(CartItem $item, int $quantity): ?CartItem
    {
        if ($quantity === 0) {
            $item->delete();

            return null;
        }

        $item->update(['quantity' => Quantity::assertValid($quantity)]);

        return $item;
    }
}
