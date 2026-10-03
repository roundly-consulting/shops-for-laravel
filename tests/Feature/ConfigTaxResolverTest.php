<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Support\Tax\ConfigTaxResolver;
use RoundlyConsulting\Shops\Support\Tax\TaxRateValue;

it('returns a basis-point value for each configured tax class', function (): void {
    $resolver = new ConfigTaxResolver;

    expect($resolver->rateFor(null, 'standard'))
        ->toBeInstanceOf(TaxRateValue::class)
        ->and($resolver->rateFor(null, 'standard')->basisPoints)->toBe(2000)
        ->and($resolver->rateFor(null, 'reduced')->basisPoints)->toBe(1000)
        ->and($resolver->rateFor(null, 'zero')->basisPoints)->toBe(0);
});

it('falls back to the standard class for an unknown tax class', function (): void {
    expect((new ConfigTaxResolver)->rateFor(null, 'made-up')->basisPoints)->toBe(2000);
});

it('falls back to the shipped standard rate when the map leaves standard out', function (): void {
    config()->set('shops.tax_classes', []);

    expect((new ConfigTaxResolver)->rateFor(null, 'anything')->basisPoints)->toBe(2000)
        ->and((new ConfigTaxResolver)->rateFor(null, 'reduced')->basisPoints)->toBe(1000)
        ->and((new ConfigTaxResolver)->rateFor(null, 'zero')->isZero())->toBeTrue();
});

it('carries the requested tax class and country on the value', function (): void {
    $value = (new ConfigTaxResolver)->rateFor(null, 'reduced', 'DE');

    expect($value->taxClass)->toBe('reduced')
        ->and($value->country)->toBe('DE');
});
