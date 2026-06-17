<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Exceptions;

final class CurrencyMismatchException extends ShopsException
{
    public static function between(string $expected, string $actual): self
    {
        return new self("Cannot operate on money in [{$actual}] using [{$expected}].");
    }
}
