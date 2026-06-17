<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RoundlyConsulting\Shops\Inventory\Enums\StockReason;
use RoundlyConsulting\Shops\Inventory\StockAdjustment;

it('has relationships', function (): void {
    expect((new StockAdjustment)->variant())->toBeInstanceOf(BelongsTo::class)
        ->and((new StockAdjustment)->reference())->toBeInstanceOf(MorphTo::class);
});

it('casts its reason to the enum', function (): void {
    $adjustment = StockAdjustment::factory()->create(['reason' => StockReason::Manual]);

    expect($adjustment->refresh()->reason)->toBe(StockReason::Manual);
});
