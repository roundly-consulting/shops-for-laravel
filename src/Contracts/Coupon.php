<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Contracts;

use RoundlyConsulting\Shops\Support\Money\Money;

/**
 * Contract a host application's coupon model implements so the shop can apply
 * discounts without depending on a concrete coupon package. The model the
 * package resolves is configured via `config('shops.orders.coupon_model')`.
 */
interface Coupon
{
    /**
     * Whether the coupon may still be applied (e.g. active and under its usage
     * limit).
     */
    public function canBeApplied(): bool;

    /**
     * Apply the coupon's discount to the given amount and return the discounted
     * amount. Implementations must not mutate the coupon's usage here.
     */
    public function apply(Money $money): Money;

    /**
     * Record that the coupon was used once (e.g. increment a usage counter).
     */
    public function recordUsage(): void;
}
