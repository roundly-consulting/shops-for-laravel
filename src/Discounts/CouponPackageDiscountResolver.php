<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Discounts;

use RoundlyConsulting\Coupons\CouponManager;
use RoundlyConsulting\Shops\Contracts\DiscountResolver;
use RoundlyConsulting\Shops\Support\Money\Money;

/**
 * Discount resolver backed by roundly-consulting/coupons-for-laravel. Looks up
 * the coupon by code, previews its discount against the goods subtotal (bridging
 * the two Money types), and reports free-shipping intent. A missing or currently
 * non-redeemable coupon yields a zero discount.
 */
final class CouponPackageDiscountResolver implements DiscountResolver
{
    public function __construct(
        private readonly CouponManager $coupons,
        private readonly MoneyBridge $bridge,
    ) {}

    public function resolve(string $code, Money $goods): DiscountResult
    {
        $currency = $goods->getCurrency()->getCode();

        $coupon = $this->coupons->find($code);

        if ($coupon === null) {
            return DiscountResult::none($currency, $code);
        }

        $couponPrice = $this->bridge->toCoupons($goods);

        if (! $coupon->isRedeemableBy(null, $couponPrice)) {
            return new DiscountResult(
                discount: Money::zero($currency),
                freeShipping: false,
                code: $code,
                found: true,
            );
        }

        return new DiscountResult(
            discount: $this->bridge->toShops($coupon->previewDiscount($couponPrice)),
            freeShipping: $coupon->isFreeShipping(),
            code: $code,
            found: true,
        );
    }
}
