<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Actions;

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Shops\Contracts\Coupon;
use RoundlyConsulting\Shops\Support\Money\Money;

/**
 * Applies a coupon to a money amount, recording its usage transactionally. When
 * the coupon can no longer be applied (expired, over its usage limit, …) the
 * original amount is returned unchanged.
 */
final class UseCoupon
{
    public function execute(Coupon $coupon, Money $money): Money
    {
        if (! $coupon->canBeApplied()) {
            return $money;
        }

        return DB::transaction(function () use ($coupon, $money): Money {
            $coupon->recordUsage();

            return $coupon->apply($money);
        });
    }
}
