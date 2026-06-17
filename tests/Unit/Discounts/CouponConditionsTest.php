<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Shops\Discounts\DataTransferObjects\CouponConditions;
use RoundlyConsulting\Shops\Support\Money\Money;

$now = Carbon::parse('2026-06-17 12:00:00');

it('passes with no conditions set', function () use ($now): void {
    expect((new CouponConditions)->passes($now))->toBeTrue();
});

it('fails when usage meets the max', function () use ($now): void {
    expect((new CouponConditions(maxUsage: 2, usage: 2))->passes($now))->toBeFalse();
});

it('fails before the start window', function () use ($now): void {
    expect((new CouponConditions(startsAt: $now->copy()->addDay()))->passes($now))->toBeFalse();
});

it('fails after the expiry window', function () use ($now): void {
    expect((new CouponConditions(expiresAt: $now->copy()->subDay()))->passes($now))->toBeFalse();
});

it('fails below the minimum spend', function () use ($now): void {
    $conditions = new CouponConditions(minimumSpend: Money::EUR(5000));

    expect($conditions->passes($now, Money::EUR(1000)))->toBeFalse()
        ->and($conditions->passes($now, Money::EUR(5000)))->toBeTrue();
});

it('ignores minimum spend when no spend is supplied', function () use ($now): void {
    $conditions = new CouponConditions(minimumSpend: Money::EUR(5000));

    expect($conditions->passes($now))->toBeTrue();
});
