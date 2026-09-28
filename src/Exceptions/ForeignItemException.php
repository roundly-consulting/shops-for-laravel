<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Exceptions;

use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Cart\CartItem;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Products\ProductVariant;

/**
 * A scoped call was handed something that belongs to another parent: a line of another cart
 * (`Shops::cart($a)->update($lineOfB, 2)`), or a return booked against an order the variant
 * was never on. Scoping is a security boundary — a request that names a cart line by id must
 * not reach into another customer's cart — so the call is refused before anything is written.
 */
final class ForeignItemException extends ShopsException
{
    /**
     * @throws self when the line is not one of the cart's.
     */
    public static function assertInCart(Cart $cart, CartItem $item): void
    {
        if ((string) $item->cart_id !== (string) $cart->getKey()) {
            throw new self("Cart line [{$item->getKey()}] does not belong to cart [{$cart->getKey()}].");
        }
    }

    public static function notOnOrder(Order $order, ProductVariant $variant): self
    {
        return new self("Variant [{$variant->sku}] is not on order [{$order->number}].");
    }
}
