<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Contracts\TaxResolver;
use RoundlyConsulting\Shops\Support\Tax\ConfigTaxResolver;

it('returns the configured rate for each tax class', function (): void {
    $resolver = new ConfigTaxResolver;

    expect($resolver->rateFor('standard'))->toBe(20)
        ->and($resolver->rateFor('reduced'))->toBe(10)
        ->and($resolver->rateFor('zero'))->toBe(0);
});

it('falls back to the standard class for an unknown tax class', function (): void {
    expect((new ConfigTaxResolver)->rateFor('made-up'))->toBe(20);
});

it('falls back to zero when even the standard class is missing', function (): void {
    config()->set('shops.tax_classes', []);

    expect((new ConfigTaxResolver)->rateFor('anything'))->toBe(0);
});

it('is bound as the default tax resolver', function (): void {
    expect(resolve(TaxResolver::class))->toBeInstanceOf(ConfigTaxResolver::class);
});
