<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Discounts;

use RoundlyConsulting\Coupons\ValueObjects\Money as CouponsMoney;
use RoundlyConsulting\Shops\Support\Money\Money as ShopsMoney;

/**
 * Loss-free conversion between shops' Money and coupons-for-laravel's Money.
 * Both store an integer amount of minor units plus an ISO currency code, so the
 * bridge is an exact round-trip.
 */
final class MoneyBridge
{
    public function toCoupons(ShopsMoney $money): CouponsMoney
    {
        return new CouponsMoney($money->getMinorAmount(), $money->getCurrency()->getCode());
    }

    public function toShops(CouponsMoney $money): ShopsMoney
    {
        return ShopsMoney::of($money->getAmount(), $money->getCurrency());
    }
}
