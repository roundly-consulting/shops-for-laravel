<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Shops\Support\Quantity;

/**
 * Guards a line model's `quantity` on every write — create, fill, update and
 * increment — so a zero, negative, fractional or oversized line can never be stored,
 * whichever path wrote it. Rows already in the database are read as they are.
 *
 * @phpstan-require-extends Model
 */
trait HasQuantity
{
    /**
     * @return Attribute<never, int>
     */
    protected function quantity(): Attribute
    {
        return Attribute::make(set: static fn (mixed $value): int => Quantity::normalize($value));
    }
}
