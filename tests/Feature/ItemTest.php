<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RoundlyConsulting\Shops\Orders\Item;
use RoundlyConsulting\Shops\Support\Money\Money;

it('has relationships', function (): void {
    $item = new Item;

    expect($item)
        ->shop()->toBeInstanceOf(MorphTo::class)
        ->order()->toBeInstanceOf(BelongsTo::class);
});

it('casts attributes', function (): void {
    $item = Item::factory()->withEurPrice('1500')->make();

    expect($item)
        ->quantity->toBeInt()
        ->price->toBeInstanceOf(Money::class)
        ->price->getAmount()->toBe('1500')
        ->price->getCurrency()->getCode()->toBe('EUR');
});

it('persists and re-reads the money cast', function (): void {
    $item = Item::factory()->withUsdPrice('2000')->create();

    expect($item->fresh()->price)
        ->toBeInstanceOf(Money::class)
        ->getAmount()->toBe('2000')
        ->getCurrency()->getCode()->toBe('USD');
});
