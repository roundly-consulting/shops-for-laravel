<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Support;

use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the model backing an order's coupon relation, from
 * `shops.discounts.coupon_model`.
 *
 * This is shops' *own* config key, not coupons' `coupons.model` — it names the
 * model an order's `coupon_id` points at, and the config documents it as "the
 * coupons package model by default; point at your own subclass to extend it".
 * So unlike {@see ShopModel} this one narrows: an order's coupon is read through
 * the coupons API (redeemability, discount preview, free shipping), which only a
 * Coupon can answer. The toolkit's ModelResolver validates that the configured
 * value is a real Eloquent model; it cannot know it is a Coupon, so anything else
 * falls back to the coupons package model.
 */
final class CouponModel
{
    /** @return class-string<Coupon> */
    public static function class(): string
    {
        $model = ModelResolver::for('shops.discounts.coupon_model', Coupon::class);

        return is_a($model, Coupon::class, true) ? $model : Coupon::class;
    }
}
