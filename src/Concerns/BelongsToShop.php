<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Concerns;

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
}
