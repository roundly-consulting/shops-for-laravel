<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Products\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RoundlyConsulting\Shops\Products\ProductOption;

/**
 * Gives a product its configurable options (e.g. Size, Colour), each owning a
 * set of values that variants are composed from.
 *
 * @phpstan-require-extends Model
 */
trait HasOptions
{
    /**
     * @return HasMany<ProductOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class, 'product_id')->orderBy('position');
    }
}
