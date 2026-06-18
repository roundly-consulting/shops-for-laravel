<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Support\Tax\TaxRateValue;

it('converts basis points to a percentage', function (): void {
    expect((new TaxRateValue(1900))->percent())->toBe(19.0)
        ->and((new TaxRateValue(850))->percent())->toBe(8.5);
});

it('exposes the gross divisor', function (): void {
    expect((new TaxRateValue(1900))->grossDivisor())->toBe(1.19)
        ->and((new TaxRateValue(850))->grossDivisor())->toBe(1.085);
});

it('builds and reports a zero rate', function (): void {
    $zero = TaxRateValue::zero('reduced');

    expect($zero->basisPoints)->toBe(0)
        ->and($zero->taxClass)->toBe('reduced')
        ->and($zero->isZero())->toBeTrue()
        ->and((new TaxRateValue(100))->isZero())->toBeFalse();
});
