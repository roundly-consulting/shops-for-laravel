<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Cart\Actions;

use RoundlyConsulting\Shops\Cart\Cart;

final class ClearCart
{
    public function execute(Cart $cart): Cart
    {
        $cart->items()->delete();

        return $cart->refresh();
    }
}
