<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\ValueObjects\Money as CouponsMoney;
use RoundlyConsulting\Shops\Contracts\DiscountResolver;
use RoundlyConsulting\Shops\Discounts\CouponPackageDiscountResolver;
use RoundlyConsulting\Shops\Discounts\MoneyBridge;
use RoundlyConsulting\Shops\Support\Money\Money;

it('binds the coupons-backed resolver by default', function (): void {
    expect(app(DiscountResolver::class))->toBeInstanceOf(CouponPackageDiscountResolver::class);
});

it('resolves a percentage discount', function (): void {
    Coupon::factory()->percentage(25)->active()->create(['code' => 'QUARTER']);

    $result = app(DiscountResolver::class)->resolve('QUARTER', Money::EUR(1000));

    expect($result->discount->getAmount())->toBe('250')
        ->and($result->found)->toBeTrue()
        ->and($result->freeShipping)->toBeFalse();
});

it('resolves a fixed discount', function (): void {
    Coupon::factory()->fixed(300)->active()->create(['code' => 'TENNER']);

    $result = app(DiscountResolver::class)->resolve('TENNER', Money::EUR(1000));

    expect($result->discount->getAmount())->toBe('300');
});

it('reports free shipping for a free-shipping coupon', function (): void {
    Coupon::factory()->freeShipping()->active()->create(['code' => 'FREESHIP']);

    $result = app(DiscountResolver::class)->resolve('FREESHIP', Money::EUR(1000));

    expect($result->freeShipping)->toBeTrue()
        ->and($result->discount->getAmount())->toBe('0');
});

it('caps the discount via the coupon max_discount', function (): void {
    Coupon::factory()->percentage(50)->active()->create(['code' => 'CAPPED', 'max_discount' => 200]);

    $result = app(DiscountResolver::class)->resolve('CAPPED', Money::EUR(1000));

    expect($result->discount->getAmount())->toBe('200');
});

it('returns a not-found result for an unknown code', function (): void {
    $result = app(DiscountResolver::class)->resolve('NOPE', Money::EUR(1000));

    expect($result->found)->toBeFalse()
        ->and($result->hasDiscount())->toBeFalse();
});

it('yields a zero discount for a non-redeemable coupon', function (): void {
    Coupon::factory()->percentage(10)->expired()->create(['code' => 'OLD']);

    $result = app(DiscountResolver::class)->resolve('OLD', Money::EUR(1000));

    expect($result->found)->toBeTrue()
        ->and($result->discount->getAmount())->toBe('0');
});

it('round-trips money across the bridge', function (): void {
    $bridge = new MoneyBridge;

    $coupons = $bridge->toCoupons(Money::EUR(1234));
    expect($coupons)->toBeInstanceOf(CouponsMoney::class)
        ->and($coupons->getAmount())->toBe(1234)
        ->and($coupons->getCurrency())->toBe('EUR');

    $shops = $bridge->toShops(new CouponsMoney(1234, 'EUR'));
    expect($shops->getMinorAmount())->toBe(1234)
        ->and($shops->getCurrency()->getCode())->toBe('EUR');
});
