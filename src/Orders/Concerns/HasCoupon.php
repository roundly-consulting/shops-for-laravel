<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RoundlyConsulting\Coupons\Models\Coupon;

/**
 * The order's optional coupon, resolved from coupons-for-laravel (swap the model
 * via `shops.discounts.coupon_model`).
 *
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
        $model = config('shops.discounts.coupon_model', Coupon::class);

        return $this->belongsTo($model);
    }
}
