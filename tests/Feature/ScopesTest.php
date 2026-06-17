<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Products\Category;
use RoundlyConsulting\Shops\Products\Product;
use RoundlyConsulting\Shops\Products\ProductVariant;

it('scopes to published records', function (): void {
    Carbon::setTestNow('2026-06-17 12:00:00');

    $published = Product::factory()->create(['published_at' => now()->subDay()]);
    Product::factory()->create(['published_at' => null]);
    Product::factory()->create(['published_at' => now()->addDay()]);

    expect(Product::query()->published()->pluck('id')->all())->toBe([$published->id]);

    Carbon::setTestNow();
});

it('scopes to unpublished records', function (): void {
    Carbon::setTestNow('2026-06-17 12:00:00');

    Product::factory()->create(['published_at' => now()->subDay()]);
    $null = Product::factory()->create(['published_at' => null]);
    $future = Product::factory()->create(['published_at' => now()->addDay()]);

    expect(Product::query()->unpublished()->pluck('id')->all())
        ->toEqualCanonicalizing([$null->id, $future->id]);

    Carbon::setTestNow();
});

it('reports whether a record is published', function (): void {
    Carbon::setTestNow('2026-06-17 12:00:00');

    expect(Product::factory()->create(['published_at' => now()->subDay()])->isPublished())->toBeTrue()
        ->and(Product::factory()->create(['published_at' => null])->isPublished())->toBeFalse();

    Carbon::setTestNow();
});

it('scopes to a given shop', function (): void {
    $shopA = Category::factory()->create();
    $shopB = Category::factory()->create();

    $forA = Product::factory()->create();
    $forA->shop()->associate($shopA)->save();
    $forB = Product::factory()->create();
    $forB->shop()->associate($shopB)->save();

    expect(Product::query()->forShop($shopA)->pluck('id')->all())->toBe([$forA->id]);
});

it('scopes to in-stock variants', function (): void {
    $available = ProductVariant::factory()->create(['stock' => 5, 'reserved' => 0]);
    ProductVariant::factory()->create(['stock' => 0, 'reserved' => 0]);

    expect(ProductVariant::query()->inStock()->pluck('id'))->toContain($available->id);
});

it('binds an order by its number as the route key', function (): void {
    $order = Order::factory()->create();

    expect((new Order)->getRouteKeyName())->toBe('number')
        ->and((new Order)->resolveRouteBinding($order->number)?->is($order))->toBeTrue();
});
