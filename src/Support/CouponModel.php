<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Support;

use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the model backing an order's coupon relation, from
 * `shops.discounts.coupon_model`.
 *
 * Absent config resolves the packaged model; anything else must be that model or a subclass of
 * it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key — a
 * foreign class is never silently replaced.
 */
final class CouponModel
{
    /** @return class-string<Coupon> */
    public static function class(): string
    {
        return ModelResolver::for('shops.discounts.coupon_model', Coupon::class);
    }
}
