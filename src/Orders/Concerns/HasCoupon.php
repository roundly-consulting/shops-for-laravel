<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Shops\Support\CouponModel;

/**
 * The order's optional coupon, resolved from coupons-for-laravel (swap the model
 * via `shops.discounts.coupon_model`).
 *
 * @phpstan-require-extends Model
 *
 * @property-read Coupon|null $coupon
 */
trait HasCoupon
{
    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(CouponModel::class());
    }
}
