<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Inventory\Exceptions;

use RoundlyConsulting\Shops\Exceptions\ShopsException;
use RoundlyConsulting\Shops\Products\ProductVariant;

final class InsufficientStockException extends ShopsException
{
    public static function for(ProductVariant $variant, int $requested): self
    {
        return new self(
            "Insufficient stock for variant [{$variant->sku}]: requested {$requested}, available {$variant->availableStock()}.",
        );
    }
}
