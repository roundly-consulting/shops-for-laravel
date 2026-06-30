<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Orders\Enums\PriceType;

it('exposes readable labels via the enums trait', function (): void {
    expect(PriceType::Net->label())->toBe('Net')
        ->and(PriceType::Gross->readable())->toBe('Gross')
        ->and(PriceType::labels()->all())->toBe(['Net', 'Gross']);
});

it('builds select options via the enums trait', function (): void {
    expect(PriceType::values()->all())->toBe(['net', 'gross'])
        ->and(PriceType::toOptions()->all())->toBe([
            'net' => 'Net',
            'gross' => 'Gross',
        ]);
});

it('builds a validation rule via the enums trait', function (): void {
    expect(PriceType::validationRule())->toBe('in:net,gross');
});

it('resolves cases by name via the enums trait', function (): void {
    expect(PriceType::tryFromName('Gross'))->toBe(PriceType::Gross)
        ->and(PriceType::tryFromName('Missing'))->toBeNull();
});
