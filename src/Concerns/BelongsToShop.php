<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RoundlyConsulting\Shops\Shops\CurrentShop;
use RoundlyConsulting\Shops\Shops\Shop;

/**
 * Marks a model as owned by a {@see Shop} tenant through a plain `shop_id`
 * foreign key.
 *
 * When a shop is bound via {@see CurrentShop}, a new record's `shop_id` is
 * auto-filled on create — unless it was set explicitly, which always wins. With
 * no current shop bound, `shop_id` is left untouched (single-shop apps that
 * never touch tenancy keep working).
 *
 * @phpstan-require-extends Model
 */
trait BelongsToShop
{
    public static function bootBelongsToShop(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getAttribute('shop_id') === null
                && ($id = app(CurrentShop::class)->id()) !== null) {
                $model->setAttribute('shop_id', $id);
            }
        });
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::resolveModelClass(), 'shop_id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForShop(Builder $query, Model|int $shop): void
    {
        $query->where('shop_id', $shop instanceof Model ? $shop->getKey() : $shop);
    }
}
