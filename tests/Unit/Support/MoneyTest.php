<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Exceptions\CurrencyMismatchException;
use RoundlyConsulting\Shops\Exceptions\InvalidCurrencyException;
use RoundlyConsulting\Shops\Support\Money\Money;

it('builds money from named constructors', function (): void {
    expect(Money::EUR(1000))
        ->getAmount()->toBe('1000')
        ->getCurrency()->getCode()->toBe('EUR')
        ->and(Money::USD(250)->getCurrency()->getCode())->toBe('USD')
        ->and(Money::of(50, 'gbp')->getCurrency()->getCode())->toBe('GBP');
});

it('rejects an invalid currency code', function (): void {
    Money::of(100, 'EURO');
})->throws(InvalidCurrencyException::class);

it('adds and subtracts money of the same currency', function (): void {
    expect(Money::EUR(1000)->add(Money::EUR(250))->getAmount())->toBe('1250')
        ->and(Money::EUR(1000)->subtract(Money::EUR(250))->getAmount())->toBe('750');
});

it('sums a list of money values', function (): void {
    expect(Money::sum(Money::EUR(100), Money::EUR(200), Money::EUR(300))->getAmount())
        ->toBe('600');
});

it('multiplies and divides money', function (): void {
    expect(Money::EUR(1000)->multiply(20)->divide(100)->getAmount())->toBe('200');
});

it('refuses to combine mismatched currencies', function (): void {
    Money::EUR(1000)->add(Money::USD(100));
})->throws(CurrencyMismatchException::class);

it('compares two money values for equality', function (): void {
    expect(Money::EUR(1000)->equals(Money::EUR(1000)))->toBeTrue()
        ->and(Money::EUR(1000)->equals(Money::EUR(999)))->toBeFalse()
        ->and(Money::EUR(1000)->equals(Money::USD(1000)))->toBeFalse();
});
