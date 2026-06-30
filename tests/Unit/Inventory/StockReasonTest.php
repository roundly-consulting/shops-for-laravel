<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Inventory\Enums\StockReason;

it('identifies reasons that affect the reserved quantity', function (StockReason $reason, bool $reserved): void {
    expect($reason->affectsReserved())->toBe($reserved);
})->with([
    [StockReason::Reserved, true],
    [StockReason::Released, true],
    [StockReason::Received, false],
    [StockReason::Sold, false],
    [StockReason::Returned, false],
    [StockReason::Manual, false],
]);

it('exposes labels, options and a validation rule via the enums trait', function (): void {
    expect(StockReason::Received->label())->toBe('Received')
        ->and(StockReason::labels()->all())->toBe([
            'Received', 'Sold', 'Reserved', 'Released', 'Returned', 'Manual',
        ])
        ->and(StockReason::validationRule())
        ->toBe('in:Received,Sold,Reserved,Released,Returned,Manual')
        ->and(StockReason::options()->first()->toArray())->toBe([
            'value' => 'Received',
            'label' => 'Received',
            'name' => 'Received',
        ]);
});
