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
