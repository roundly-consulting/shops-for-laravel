<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Shops\Contracts\Coupon as CouponContract;
use RoundlyConsulting\Shops\Discounts\Coupon;
use RoundlyConsulting\Shops\Discounts\Enums\DiscountType;
use RoundlyConsulting\Shops\Exceptions\CurrencyMismatchException;
use RoundlyConsulting\Shops\Support\Money\Money;

it('implements the coupon contract', function (): void {
    expect(new Coupon)->toBeInstanceOf(CouponContract::class);
});

it('applies a percentage discount', function (): void {
    $coupon = Coupon::factory()->percentage(25)->create();

    expect($coupon->apply(Money::EUR(1000))->getAmount())->toBe('750');
});

it('applies a fixed discount', function (): void {
    $coupon = Coupon::factory()->fixed(300, 'EUR')->create();

    expect($coupon->apply(Money::EUR(1000))->getAmount())->toBe('700');
});

it('never discounts a fixed coupon below zero', function (): void {
    $coupon = Coupon::factory()->fixed(1500, 'EUR')->create();

    expect($coupon->apply(Money::EUR(1000))->getAmount())->toBe('0');
});

it('throws when a fixed coupon currency mismatches', function (): void {
    $coupon = Coupon::factory()->fixed(300, 'USD')->create();

    expect(fn () => $coupon->apply(Money::EUR(1000)))
        ->toThrow(CurrencyMismatchException::class);
});

it('records usage', function (): void {
    $coupon = Coupon::factory()->create(['usage' => 0]);

    $coupon->recordUsage();

    expect($coupon->fresh()->usage)->toBe(1);
});

it('can be applied within its conditions', function (): void {
    $coupon = Coupon::factory()->percentage(10)->create();

    expect($coupon->canBeApplied())->toBeTrue();
});

it('cannot be applied once exhausted', function (): void {
    $coupon = Coupon::factory()->exhausted()->create();

    expect($coupon->canBeApplied())->toBeFalse();
});

it('cannot be applied before it starts', function (): void {
    $coupon = Coupon::factory()->notYetStarted()->create();

    expect($coupon->canBeApplied())->toBeFalse();
});

it('cannot be applied after it expires', function (): void {
    Carbon::setTestNow('2026-06-17 12:00:00');
    $coupon = Coupon::factory()->expired()->create();

    expect($coupon->canBeApplied())->toBeFalse();

    Carbon::setTestNow();
});

it('cannot be applied below its minimum spend', function (): void {
    $coupon = Coupon::factory()->percentage(10)->withMinimumSpend(5000, 'EUR')->create();

    expect($coupon->canBeApplied(Money::EUR(1000)))->toBeFalse()
        ->and($coupon->canBeApplied(Money::EUR(6000)))->toBeTrue();
});

it('casts its type to the discount enum', function (): void {
    $coupon = Coupon::factory()->create(['type' => DiscountType::Fixed, 'currency' => 'EUR']);

    expect($coupon->fresh()->type)->toBe(DiscountType::Fixed);
});
