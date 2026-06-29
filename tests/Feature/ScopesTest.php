<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Orders\Item;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Products\Category;
use RoundlyConsulting\Shops\Products\Product;
use RoundlyConsulting\Shops\Products\ProductVariant;
use RoundlyConsulting\Shops\Shops\CurrentShop;
use RoundlyConsulting\Shops\Shops\Shop;

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

it('scopes to a given shop by model and by id', function (): void {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();

    $forA = Product::factory()->create(['shop_id' => $shopA->id]);
    Product::factory()->create(['shop_id' => $shopB->id]);

    expect(Product::query()->forShop($shopA)->pluck('id')->all())->toBe([$forA->id])
        ->and(Product::query()->forShop($shopA->id)->pluck('id')->all())->toBe([$forA->id]);
});

it('auto-fills shop_id from the bound current shop on create', function (): void {
    $shop = Shop::factory()->create();
    app(CurrentShop::class)->set($shop);

    $product = Product::create(['name' => 'Still Water']);

    expect($product->shop_id)->toBe($shop->id);
});

it('lets an explicit shop_id win over the bound current shop', function (): void {
    $bound = Shop::factory()->create();
    $explicit = Shop::factory()->create();
    app(CurrentShop::class)->set($bound);

    $product = Product::create(['name' => 'Sparkling Water', 'shop_id' => $explicit->id]);

    expect($product->shop_id)->toBe($explicit->id);
});

it('leaves shop_id null when no current shop is bound', function (): void {
    $product = Product::create(['name' => 'Tonic']);

    expect($product->shop_id)->toBeNull();
});

it('resolves a Shop instance for each owned model', function (callable $make): void {
    $shop = Shop::factory()->create();

    $model = $make($shop);

    expect($model->shop)->toBeInstanceOf(Shop::class)
        ->and($model->shop->is($shop))->toBeTrue();
})->with([
    'product' => [fn (Shop $shop) => Product::factory()->create(['shop_id' => $shop->id])],
    'category' => [fn (Shop $shop) => Category::factory()->create(['shop_id' => $shop->id])],
    'cart' => [fn (Shop $shop) => Cart::factory()->create(['shop_id' => $shop->id])],
    'order' => [fn (Shop $shop) => Order::factory()->create(['shop_id' => $shop->id])],
    'item' => [fn (Shop $shop) => Item::factory()->create(['shop_id' => $shop->id])],
]);

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
