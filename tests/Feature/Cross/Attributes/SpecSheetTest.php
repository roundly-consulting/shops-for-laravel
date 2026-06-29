<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\Exceptions\InvalidAttributeValueException;
use RoundlyConsulting\Attributes\Exceptions\UnknownAttributeException;
use RoundlyConsulting\Shops\Products\Product;

it('attaches typed spec attributes and reads them back', function (): void {
    $product = Product::factory()->create();

    $product->attachAttribute('material', 'wool');
    $product->attachAttribute('weight', 500);

    expect($product->attr('material')->string())->toBe('wool')
        ->and($product->attr('weight')->int())->toBe(500);
});

it('rejects an unknown attribute in strict mode', function (): void {
    $product = Product::factory()->create();

    expect(fn () => $product->attachAttribute('unknown_spec', 'x'))
        ->toThrow(UnknownAttributeException::class);
});

it('rejects an invalid typed value', function (): void {
    $product = Product::factory()->create();

    expect(fn () => $product->attachAttribute('weight', 'not-a-number'))
        ->toThrow(InvalidAttributeValueException::class);
});

it('filters products by an attribute value', function (): void {
    $wool = Product::factory()->create();
    $cotton = Product::factory()->create();
    $wool->attachAttribute('material', 'wool');
    $cotton->attachAttribute('material', 'cotton');

    $ids = Product::query()->whereAttribute('material', 'wool')->pluck('id')->all();

    expect($ids)->toBe([$wool->id]);
});

it('orders products by an attribute value', function (): void {
    $light = Product::factory()->create();
    $heavy = Product::factory()->create();
    $light->attachAttribute('weight', 100);
    $heavy->attachAttribute('weight', 900);

    $ids = Product::query()->orderByAttribute('weight', 'desc')->pluck('id')->all();

    expect($ids)->toBe([$heavy->id, $light->id]);
});

it('filters products by an attribute range', function (): void {
    $a = Product::factory()->create();
    $b = Product::factory()->create();
    $a->attachAttribute('weight', 150);
    $b->attachAttribute('weight', 800);

    $ids = Product::query()->whereAttributeBetween('weight', 100, 500)->pluck('id')->all();

    expect($ids)->toBe([$a->id]);
});
