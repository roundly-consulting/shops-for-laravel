<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Exceptions;

final class InvalidCurrencyException extends ShopsException
{
    public static function forCode(string $code): self
    {
        return new self("[{$code}] is not a valid ISO 4217 currency code.");
    }
}
