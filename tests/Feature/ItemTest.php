<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Orders\Item;

it('has relationships', function (): void {
    $item = new Item;

    expect($item)
        ->shop()->toBeInstanceOf(BelongsTo::class)
        ->order()->toBeInstanceOf(BelongsTo::class);
});

it('casts attributes', function (): void {
    $item = Item::factory()->withEurPrice('1500')->make();

    expect($item)
        ->quantity->toBeInt()
        ->price->toBeInstanceOf(Money::class)
        ->price->minor()->toBe('1500')
        ->price->currency()->code->toBe('EUR');
});

it('persists and re-reads the money cast', function (): void {
    $item = Item::factory()->withUsdPrice('2000')->create();

    expect($item->fresh()->price)
        ->toBeInstanceOf(Money::class)
        ->minor()->toBe('2000')
        ->currency()->code->toBe('USD');
});
