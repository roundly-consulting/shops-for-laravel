<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Shops\CurrentShop;
use RoundlyConsulting\Shops\Shops\Shop;

it('round-trips a model through set, get, id and forget', function (): void {
    $shop = Shop::factory()->create();
    $context = app(CurrentShop::class);

    $context->set($shop);

    expect($context->get()?->is($shop))->toBeTrue()
        ->and($context->id())->toBe($shop->id);

    $context->forget();

    expect($context->get())->toBeNull()
        ->and($context->id())->toBeNull();
});

it('accepts an int id and lazily resolves the model', function (): void {
    $shop = Shop::factory()->create();
    $context = app(CurrentShop::class);

    $context->set($shop->id);

    expect($context->id())->toBe($shop->id)
        ->and($context->get()?->is($shop))->toBeTrue();
});

it('scopes a current shop within run and restores the previous binding', function (): void {
    $previous = Shop::factory()->create();
    $scoped = Shop::factory()->create();
    $context = app(CurrentShop::class);

    $context->set($previous);

    $seen = $context->run($scoped, fn (): ?int => $context->id());

    expect($seen)->toBe($scoped->id)
        ->and($context->id())->toBe($previous->id);
});

it('restores the previous binding even when the callback throws', function (): void {
    $previous = Shop::factory()->create();
    $scoped = Shop::factory()->create();
    $context = app(CurrentShop::class);

    $context->set($previous);

    expect(fn () => $context->run($scoped, function (): void {
        throw new RuntimeException('boom');
    }))->toThrow(RuntimeException::class);

    expect($context->id())->toBe($previous->id);
});

it('exposes the bound shop through the static helper', function (): void {
    expect(Shop::current())->toBeNull();

    $shop = Shop::factory()->create();
    app(CurrentShop::class)->set($shop);

    expect(Shop::current()?->is($shop))->toBeTrue();
});
