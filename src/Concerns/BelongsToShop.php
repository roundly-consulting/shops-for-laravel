<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @phpstan-require-extends Model
 */
trait BelongsToShop
{
    /**
     * @return MorphTo<Model, $this>
     */
    public function shop(): MorphTo
    {
        return $this->morphTo('shop');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForShop(Builder $query, Model $shop): void
    {
        $query->whereMorphedTo('shop', $shop);
    }
}
