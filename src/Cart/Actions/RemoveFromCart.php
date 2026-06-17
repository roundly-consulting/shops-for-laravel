<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Cart\Actions;

use RoundlyConsulting\Shops\Cart\CartItem;

final class RemoveFromCart
{
    public function execute(CartItem $item): void
    {
        $item->delete();
    }
}
