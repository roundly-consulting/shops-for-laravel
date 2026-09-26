<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Discounts;

use RoundlyConsulting\Coupons\CouponManager;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Contracts\DiscountResolver;

/**
 * Discount resolver backed by roundly-consulting/coupons-for-laravel. Looks up
 * the coupon by code, previews its discount against the goods subtotal (both
 * packages share money-for-laravel's Money), and reports free-shipping intent. A
 * missing or currently non-redeemable coupon yields a zero discount.
 */
final class CouponPackageDiscountResolver implements DiscountResolver
{
    public function __construct(
        private readonly CouponManager $coupons,
    ) {}

    public function resolve(string $code, Money $goods): DiscountResult
    {
        $coupon = $this->coupons->find($code);

        if ($coupon === null) {
            return DiscountResult::none($goods->currency(), $code);
        }

        if (! $coupon->isRedeemableBy(null, $goods)) {
            return new DiscountResult(
                discount: Money::zero($goods->currency()),
                freeShipping: false,
                code: $code,
                found: true,
            );
        }

        return new DiscountResult(
            discount: $coupon->previewDiscount($goods),
            freeShipping: $coupon->isFreeShipping(),
            code: $code,
            found: true,
            source: $coupon->discount($goods->currency()),
        );
    }
}
