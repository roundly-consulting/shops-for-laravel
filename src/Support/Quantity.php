<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Support;

use RoundlyConsulting\Shops\Exceptions\InvalidQuantityException;

/**
 * The rules for a cart or order line quantity: a whole number of items from 1 to
 * {@see self::MAX} — the range of the `smallint` quantity columns, so a line that would not
 * fit fails the same way on every engine (SQLite would otherwise store it as-is).
 */
final class Quantity
{
    public const int MIN = 1;

    public const int MAX = 32767;

    /**
     * @throws InvalidQuantityException when the quantity is below 1 or above MAX.
     */
    public static function assertValid(int $quantity): int
    {
        if ($quantity < self::MIN) {
            throw InvalidQuantityException::notPositive($quantity);
        }

        if ($quantity > self::MAX) {
            throw InvalidQuantityException::tooLarge($quantity, self::MAX);
        }

        return $quantity;
    }

    /**
     * A quantity as it arrives at a model: an int, or an integer string (request input).
     * Floats, fractions and anything else are refused, never rounded.
     *
     * @throws InvalidQuantityException
     */
    public static function normalize(mixed $value): int
    {
        if (is_string($value) && preg_match('/^-?\d{1,18}$/', $value) === 1) {
            $value = (int) $value;
        }

        if (! is_int($value)) {
            throw InvalidQuantityException::notAnInteger($value);
        }

        return self::assertValid($value);
    }
}
