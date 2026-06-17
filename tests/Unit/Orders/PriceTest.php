<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Orders\DataTransferObjects\Price;
use RoundlyConsulting\Shops\Support\Money\Money;
use RoundlyConsulting\Shops\Tests\Fixtures\TestCoupon;

it('holds values in public properties', function (): void {
    $price = new Price(
        price: Money::EUR(1000),
        shipping: Money::EUR(500),
        taxRate: 20,
    );

    expect($price)
        ->price->getAmount()->toBe('1000')
        ->shipping->getAmount()->toBe('500')
        ->taxRate->toBe(20);
});

it('calculates tax price with and without shipping', function (): void {
    $withoutShipping = new Price(Money::EUR(1000), Money::EUR(0), 20);
    $withShipping = new Price(Money::EUR(1000), Money::EUR(500), 20);

    expect($withoutShipping->getTaxPrice()->getAmount())->toBe('200')
        ->and($withShipping->getTaxPrice()->getAmount())->toBe('300');
});

it('calculates final price with shipping and tax', function (): void {
    $price = new Price(Money::EUR(1000), Money::EUR(500), 20);

    expect($price->getFinalPrice()->getAmount())->toBe('1800');
});

it('applies a coupon discount to the price', function (): void {
    $coupon = new TestCoupon(['value' => 10, 'usage' => 0, 'max_usage' => 5]);

    $price = new Price(Money::EUR(1000), Money::EUR(500), 20, $coupon);

    expect($price->getDiscountValue()->getAmount())->toBe('100')
        ->and($price->getPriceAfterDiscount()->getAmount())->toBe('900');
});

it('reports a zero discount when no coupon is set', function (): void {
    $price = new Price(Money::EUR(1000), Money::EUR(0), 20);

    expect($price->getDiscountValue()->getAmount())->toBe('0')
        ->and($price->getPriceAfterDiscount()->getAmount())->toBe('1000');
});
