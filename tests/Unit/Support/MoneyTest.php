<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Exceptions\CurrencyMismatchException;
use RoundlyConsulting\Shops\Exceptions\DivisionByZeroException;
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

it('throws when dividing by zero', function (): void {
    Money::EUR(1000)->divide(0);
})->throws(DivisionByZeroException::class);

it('builds a zero amount and reports its sign', function (): void {
    expect(Money::zero('EUR'))
        ->getAmount()->toBe('0')
        ->getCurrency()->getCode()->toBe('EUR')
        ->isZero()->toBeTrue()
        ->isNegative()->toBeFalse()
        ->isPositive()->toBeFalse();

    expect(Money::EUR(5))
        ->isZero()->toBeFalse()
        ->isNegative()->toBeFalse()
        ->isPositive()->toBeTrue();

    expect(Money::EUR(-5))
        ->isNegative()->toBeTrue()
        ->isPositive()->toBeFalse();
});

it('exposes the raw minor amount', function (): void {
    expect(Money::EUR(1234)->getMinorAmount())->toBe(1234);
});

it('compares two money values returning -1, 0 or 1', function (): void {
    expect(Money::EUR(100)->compareTo(Money::EUR(200)))->toBe(-1)
        ->and(Money::EUR(200)->compareTo(Money::EUR(200)))->toBe(0)
        ->and(Money::EUR(300)->compareTo(Money::EUR(200)))->toBe(1);
});

it('refuses to compare mismatched currencies', function (): void {
    Money::EUR(100)->compareTo(Money::USD(100));
})->throws(CurrencyMismatchException::class);

it('rounds half-up when multiplying and dividing', function (): void {
    // 101 * 0.5 = 50.5 -> 51 (half-up)
    expect(Money::EUR(101)->multiply(0.5)->getAmount())->toBe('51')
        ->and(Money::EUR(5)->divide(2)->getAmount())->toBe('3');
});
