<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @phpstan-require-extends Model
 */
trait HasCoupon
{
    /**
     * @return BelongsTo<Model, $this>
     */
    public function coupon(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = config('shops.orders.coupon_model');

        return $this->belongsTo($model);
    }
}
