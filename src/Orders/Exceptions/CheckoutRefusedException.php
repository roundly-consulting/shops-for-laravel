<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Exceptions;

use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Cart\CartItem;
use RoundlyConsulting\Shops\Exceptions\ShopsException;

/**
 * A cart that cannot become an order: it is empty (nothing to buy — also what a second,
 * double-submitted checkout of the same cart finds), or one of its lines points at a variant
 * that was deleted or soft-deleted since it was added. Thrown before anything is written, so
 * the cart stays as it was; `$cartItem` names the offending line for the storefront to show.
 */
final class CheckoutRefusedException extends ShopsException
{
    public function __construct(
        string $message,
        public readonly ?CartItem $cartItem = null,
    ) {
        parent::__construct($message);
    }

    public static function emptyCart(Cart $cart): self
    {
        return new self("Cart [{$cart->getKey()}] is empty: there is nothing to check out.");
    }

    public static function lineUnavailable(CartItem $line): self
    {
        return new self(
            "Cart line [{$line->getKey()}] ({$line->name}) is no longer available: its variant was removed.",
            $line,
        );
    }
}
