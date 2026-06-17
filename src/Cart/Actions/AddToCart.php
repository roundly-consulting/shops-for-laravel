<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Cart\Actions;

use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Cart\CartItem;
use RoundlyConsulting\Shops\Products\ProductVariant;

final class AddToCart
{
    public function execute(Cart $cart, ProductVariant $variant, int $quantity = 1): CartItem
    {
        return $cart->add($variant, $quantity);
    }
}
