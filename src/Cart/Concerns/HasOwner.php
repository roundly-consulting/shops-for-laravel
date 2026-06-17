<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Cart\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Gives a cart an optional polymorphic owner (e.g. a User) so it can belong to
 * an authenticated customer, while a nullable guest `token` supports anonymous
 * carts and merge-on-login.
 *
 * @phpstan-require-extends Model
 */
trait HasOwner
{
    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function isGuest(): bool
    {
        return $this->getAttribute('owner_id') === null;
    }
}
