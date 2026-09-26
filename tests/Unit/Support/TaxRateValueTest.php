<?php

declare(strict_types=1);

use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Money\Tax\TaxRate;
use RoundlyConsulting\Shops\Support\Tax\TaxRateValue;

it('converts basis points to a money percentage', function (): void {
    expect((new TaxRateValue(1900))->percentage()->value())->toBe('19')
        ->and((new TaxRateValue(850))->percentage()->value())->toBe('8.5');
});

it('builds the exact money tax rate, keeping its label', function (): void {
    $rate = (new TaxRateValue(850, label: 'VAT reduced'))->toTaxRate();

    expect($rate)->toBeInstanceOf(TaxRate::class)
        ->and($rate->percentage()->basisPoints())->toBe(850)
        ->and($rate->label())->toBe('VAT reduced')
        // 999 × 0.085 = 84.915 → 85; 999 × 100 / 108.5 = 920.74 → 921, tax 78.
        ->and($rate->taxOnNet(Money::ofMinor(999, 'EUR'))->minor())->toBe('85')
        ->and($rate->taxInGross(Money::ofMinor(999, 'EUR'))->minor())->toBe('78');
});

it('builds and reports a zero rate', function (): void {
    $zero = TaxRateValue::zero('reduced');

    expect($zero->basisPoints)->toBe(0)
        ->and($zero->taxClass)->toBe('reduced')
        ->and($zero->isZero())->toBeTrue()
        ->and($zero->toTaxRate()->isZero())->toBeTrue()
        ->and((new TaxRateValue(100))->isZero())->toBeFalse();
});
