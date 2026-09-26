<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Exceptions;

use RoundlyConsulting\Shops\Inventory\Enums\StockReason;

/**
 * A line quantity or stock delta that makes no sense: a line of zero, fewer than zero or a
 * fraction of an item, more than the quantity columns hold, or a stock delta whose sign
 * contradicts its reason (receiving −5, selling +1). Thrown before anything is written.
 */
final class InvalidQuantityException extends ShopsException
{
    public static function notPositive(int $quantity): self
    {
        return new self("A quantity must be at least 1, got {$quantity}.");
    }

    public static function tooLarge(int $quantity, int $max): self
    {
        return new self("A quantity must be at most {$max}, got {$quantity}.");
    }

    public static function notAnInteger(mixed $value): self
    {
        $given = is_string($value) ? '"'.mb_strimwidth($value, 0, 32, '…').'"' : get_debug_type($value);

        if (is_float($value)) {
            $given = 'float '.$value;
        }

        return new self("A quantity must be a whole number of items, got {$given}.");
    }

    public static function zeroDelta(StockReason $reason): self
    {
        return new self("A {$reason->value} stock adjustment must change the stock, got 0.");
    }

    public static function wrongSign(StockReason $reason, int $delta): self
    {
        $expected = $reason->direction() > 0 ? 'positive (it adds stock)' : 'negative (it removes stock)';

        return new self("A {$reason->value} stock adjustment must be {$expected}, got {$delta}.");
    }
}
