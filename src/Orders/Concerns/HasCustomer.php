<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Gives an order an optional polymorphic buyer (e.g. the host's User), copied
 * from the cart's owner at place-order. The buyer powers the address book, store
 * credit, and verified-purchase reviews. Nullable, so guest orders are fine.
 *
 * @phpstan-require-extends Model
 */
trait HasCustomer
{
    /**
     * @return MorphTo<Model, $this>
     */
    public function customer(): MorphTo
    {
        return $this->morphTo();
    }

    public function hasCustomer(): bool
    {
        return $this->getAttribute('customer_id') !== null;
    }
}
