<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Money\Discounts\Discount;
use RoundlyConsulting\Money\Enums\DiscountTarget;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Contracts\DiscountResolver;
use RoundlyConsulting\Shops\Discounts\CouponPackageDiscountResolver;
use RoundlyConsulting\Shops\Discounts\DiscountResult;

it('binds the coupons-backed resolver by default', function (): void {
    expect(app(DiscountResolver::class))->toBeInstanceOf(CouponPackageDiscountResolver::class);
});

it('resolves a percentage discount', function (): void {
    Coupon::factory()->percentage(2500)->active()->create(['code' => 'QUARTER']);

    $result = app(DiscountResolver::class)->resolve('QUARTER', Money::ofMinor(1000, 'EUR'));

    expect($result->discount->minor())->toBe('250')
        ->and($result->found)->toBeTrue()
        ->and($result->freeShipping)->toBeFalse();
});

it('resolves a fixed discount', function (): void {
    Coupon::factory()->fixed(300)->active()->create(['code' => 'TENNER']);

    $result = app(DiscountResolver::class)->resolve('TENNER', Money::ofMinor(1000, 'EUR'));

    expect($result->discount->minor())->toBe('300');
});

it('reports free shipping for a free-shipping coupon', function (): void {
    Coupon::factory()->freeShipping()->active()->create(['code' => 'FREESHIP']);

    $result = app(DiscountResolver::class)->resolve('FREESHIP', Money::ofMinor(1000, 'EUR'));

    expect($result->freeShipping)->toBeTrue()
        ->and($result->discount->minor())->toBe('0');
});

it('caps the discount via the coupon max_discount', function (): void {
    Coupon::factory()->cappedPercentage(5000, Money::ofMinor(200, 'EUR'))->active()->create(['code' => 'CAPPED']);

    $result = app(DiscountResolver::class)->resolve('CAPPED', Money::ofMinor(1000, 'EUR'));

    expect($result->discount->minor())->toBe('200');
});

it('returns a not-found result for an unknown code', function (): void {
    $result = app(DiscountResolver::class)->resolve('NOPE', Money::ofMinor(1000, 'EUR'));

    expect($result->found)->toBeFalse()
        ->and($result->hasDiscount())->toBeFalse();
});

it('yields a zero discount for a non-redeemable coupon', function (): void {
    Coupon::factory()->percentage(1000)->expired()->create(['code' => 'OLD']);

    $result = app(DiscountResolver::class)->resolve('OLD', Money::ofMinor(1000, 'EUR'));

    expect($result->found)->toBeTrue()
        ->and($result->discount->minor())->toBe('0');
});

it('shares one money type with coupons, so a yen coupon discounts a yen cart', function (): void {
    Coupon::factory()->fixed(300, 'JPY')->active()->create(['code' => 'YEN']);

    $result = app(DiscountResolver::class)->resolve('YEN', Money::ofMinor(1200, 'JPY'));

    expect($result->discount)->toBeInstanceOf(Money::class)
        ->and((string) $result->discount)->toBe('300 JPY');
});

it('yields a zero discount in the goods currency for a coupon locked to another currency', function (): void {
    Coupon::factory()->fixed(300, 'EUR')->active()->create(['code' => 'EURO']);

    $result = app(DiscountResolver::class)->resolve('EURO', Money::ofMinor(1200, 'JPY'));

    expect($result->found)->toBeTrue()
        ->and((string) $result->discount)->toBe('0 JPY')
        ->and($result->source)->toBeNull();
});

it('exposes the coupon as a money discount for stacking', function (): void {
    Coupon::factory()->percentage(1250)->active()->create(['code' => 'EIGHTH']);
    Coupon::factory()->freeShipping()->active()->create(['code' => 'SHIP']);

    $percent = app(DiscountResolver::class)->resolve('EIGHTH', Money::ofMinor(999, 'EUR'));
    $shipping = app(DiscountResolver::class)->resolve('SHIP', Money::ofMinor(999, 'EUR'));

    expect($percent->discount->minor())->toBe('125')
        ->and($percent->source)->toBeInstanceOf(Discount::class)
        ->and($percent->source?->label())->toBe('EIGHTH')
        ->and($percent->source?->percent()?->value())->toBe('12.5')
        ->and($shipping->source?->target())->toBe(DiscountTarget::Shipping)
        ->and(DiscountResult::none('EUR')->source)->toBeNull();
});
