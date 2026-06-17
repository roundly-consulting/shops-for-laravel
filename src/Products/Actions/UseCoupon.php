<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Products\Actions;

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Shops\Contracts\Coupon;
use RoundlyConsulting\Shops\Support\Money\Money;

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
