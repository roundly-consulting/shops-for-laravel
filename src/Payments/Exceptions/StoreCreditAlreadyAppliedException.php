<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Payments\Exceptions;

use RoundlyConsulting\Shops\Exceptions\ShopsException;

final class StoreCreditAlreadyAppliedException extends ShopsException
{
    public static function forOrder(string $number): self
    {
        return new self("Store credit has already been applied to order {$number}.");
    }
}
