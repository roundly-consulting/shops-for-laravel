<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Products\Actions\UseCoupon;
use RoundlyConsulting\Shops\Support\Money\Money;
use RoundlyConsulting\Shops\Tests\Fixtures\TestCoupon;

it('records usage and returns the discounted money while the coupon can be applied', function (): void {
    $coupon = TestCoupon::factory()->create([
        'value' => 15,
        'usage' => 0,
        'max_usage' => 2,
    ]);

    $action = new UseCoupon;

    expect($action->execute($coupon, Money::EUR(500))->getAmount())->toBe('425');
    expect($coupon->fresh()->usage)->toBe(1);

    expect($action->execute($coupon->fresh(), Money::EUR(300))->getAmount())->toBe('255');
    expect($coupon->fresh()->usage)->toBe(2);
});

it('returns the original money once the coupon is exhausted', function (): void {
    $coupon = TestCoupon::factory()->create([
        'value' => 15,
        'usage' => 2,
        'max_usage' => 2,
    ]);

    $action = new UseCoupon;

    expect($action->execute($coupon, Money::EUR(1000))->getAmount())->toBe('1000');
    expect($coupon->fresh()->usage)->toBe(2);
});
