<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Orders\Item;
use RoundlyConsulting\Shops\Support\Casts\MoneyCast;
use RoundlyConsulting\Shops\Support\Money\Money;

it('returns null when the stored amount is null', function (): void {
    $cast = new MoneyCast;

    expect($cast->get(new Item, 'price', null, []))->toBeNull();
});

it('falls back to EUR when no currency is stored', function (): void {
    $cast = new MoneyCast;

    expect($cast->get(new Item, 'price', 1000, [])->getCurrency()->getCode())->toBe('EUR');
});

it('passes through a non-money value on set', function (): void {
    $cast = new MoneyCast;

    expect($cast->set(new Item, 'price', 1000, []))->toBe(['price' => 1000]);
});

it('splits a money value into amount and currency on set', function (): void {
    $cast = new MoneyCast;

    expect($cast->set(new Item, 'price', Money::USD(2500), []))
        ->toBe(['price' => '2500', 'currency' => 'USD']);
});
