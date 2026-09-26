<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Contracts;

use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Discounts\DiscountResult;

/**
 * Resolves the discount a coupon code applies to a goods subtotal. The only
 * shipped implementation is backed by roundly-consulting/coupons-for-laravel;
 * the seam exists so pricing and tests can depend on an abstraction rather than
 * the coupon package directly.
 */
interface DiscountResolver
{
    public function resolve(string $code, Money $goods): DiscountResult;
}
