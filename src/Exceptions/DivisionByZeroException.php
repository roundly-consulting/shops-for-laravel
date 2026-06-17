<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Exceptions;

final class DivisionByZeroException extends ShopsException
{
    public static function make(): self
    {
        return new self('Cannot divide a money value by zero.');
    }
}
