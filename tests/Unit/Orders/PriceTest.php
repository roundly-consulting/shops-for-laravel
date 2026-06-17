<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Orders\DataTransferObjects\Price;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\PriceLine;
use RoundlyConsulting\Shops\Orders\Enums\PriceType;
use RoundlyConsulting\Shops\Support\Money\Money;
use RoundlyConsulting\Shops\Support\Tax\ConfigTaxResolver;
use RoundlyConsulting\Shops\Tests\Fixtures\TestCoupon;

it('multiplies the line price by its quantity (G4 regression)', function (): void {
    $price = new Price(
        lines: [new PriceLine(Money::EUR(199), quantity: 2)],
        shipping: Money::zero('EUR'),
        priceType: PriceType::Net,
        taxResolver: new ConfigTaxResolver,
    );

    expect($price->getSubtotal()->getAmount())->toBe('398');
});

it('extracts tax from a gross price instead of adding on top (G1 regression)', function (): void {
    // 120 gross at 20% => net 100, tax 20.
    $price = new Price(
        lines: [new PriceLine(Money::EUR(120), taxClass: 'standard')],
        shipping: Money::zero('EUR'),
        priceType: PriceType::Gross,
        taxResolver: new ConfigTaxResolver,
    );

    expect($price->getTaxPrice()->getAmount())->toBe('20')
        ->and($price->getNetPrice()->getAmount())->toBe('100')
        ->and($price->getFinalPrice()->getAmount())->toBe('120');
});

it('adds tax on top for a net price type', function (): void {
    $price = new Price(
        lines: [new PriceLine(Money::EUR(1000), taxClass: 'standard')],
        shipping: Money::EUR(500),
        priceType: PriceType::Net,
        taxResolver: new ConfigTaxResolver,
    );

    expect($price->getTaxPrice()->getAmount())->toBe('200')
        ->and($price->getNetPrice()->getAmount())->toBe('1000')
        ->and($price->getFinalPrice()->getAmount())->toBe('1700');
});

it('sums mixed tax classes correctly', function (): void {
    $price = new Price(
        lines: [
            new PriceLine(Money::EUR(1000), taxClass: 'standard'), // +200
            new PriceLine(Money::EUR(1000), taxClass: 'reduced'),  // +100
            new PriceLine(Money::EUR(1000), taxClass: 'zero'),     // +0
        ],
        shipping: Money::zero('EUR'),
        priceType: PriceType::Net,
        taxResolver: new ConfigTaxResolver,
    );

    expect($price->getTaxPrice()->getAmount())->toBe('300');
});

it('adds shipping exactly once', function (): void {
    $price = new Price(
        lines: [new PriceLine(Money::EUR(1000), taxClass: 'zero')],
        shipping: Money::EUR(500),
        priceType: PriceType::Net,
        taxResolver: new ConfigTaxResolver,
    );

    expect($price->getFinalPrice()->getAmount())->toBe('1500');
});

it('returns a zero price in the default currency for an empty line set', function (): void {
    $price = new Price(
        lines: [],
        shipping: Money::zero('USD'),
        priceType: PriceType::Gross,
        taxResolver: new ConfigTaxResolver,
        currency: 'USD',
    );

    expect($price->getSubtotal()->getAmount())->toBe('0')
        ->and($price->getSubtotal()->getCurrency()->getCode())->toBe('USD')
        ->and($price->getTaxPrice()->getAmount())->toBe('0')
        ->and($price->getFinalPrice()->getAmount())->toBe('0');
});

it('applies a coupon discount and scales tax proportionally', function (): void {
    $coupon = new TestCoupon(['value' => 10, 'usage' => 0, 'max_usage' => 5]);

    $price = new Price(
        lines: [new PriceLine(Money::EUR(1000), taxClass: 'standard')],
        shipping: Money::zero('EUR'),
        priceType: PriceType::Net,
        taxResolver: new ConfigTaxResolver,
        coupon: $coupon,
    );

    expect($price->getDiscountValue()->getAmount())->toBe('100')
        ->and($price->getPriceAfterDiscount()->getAmount())->toBe('900')
        ->and($price->getTaxPrice()->getAmount())->toBe('180');
});

it('reports a zero discount when no coupon is set', function (): void {
    $price = new Price(
        lines: [new PriceLine(Money::EUR(1000), taxClass: 'zero')],
        shipping: Money::zero('EUR'),
        priceType: PriceType::Net,
        taxResolver: new ConfigTaxResolver,
    );

    expect($price->getDiscountValue()->getAmount())->toBe('0')
        ->and($price->getPriceAfterDiscount()->getAmount())->toBe('1000');
});

it('defaults the price type to gross and uses the bound resolver', function (): void {
    $price = new Price(
        lines: [new PriceLine(Money::EUR(120))],
        shipping: Money::zero('EUR'),
    );

    expect($price->priceType)->toBe(PriceType::Gross)
        ->and($price->getTaxPrice()->getAmount())->toBe('20');
});
