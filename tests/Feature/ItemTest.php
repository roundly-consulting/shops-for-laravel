<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Facades\Shops;
use RoundlyConsulting\Shops\Orders\Item;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Products\ProductVariant;
use RoundlyConsulting\Shops\Shops\Shop;

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

it('puts an item in its order shop', function (): void {
    $shop = Shop::factory()->create();
    $order = Order::factory()->create(['shop_id' => $shop->getKey()]);

    $item = Item::factory()->for($order)->withEurPrice('100')->create();

    expect($item->shop_id)->toBe($shop->getKey())
        ->and(Item::query()->forShop($shop)->count())->toBe(1);
});

it('puts checked-out lines in the order shop', function (): void {
    $shop = Shop::factory()->create();
    $cart = Cart::create(['currency' => 'EUR', 'shop_id' => $shop->getKey()]);
    $cart->add(ProductVariant::factory()->withEurPrice('1000')->create(['stock' => 5]));

    $order = Shops::cart($cart)->checkout();

    expect($order->items->first()?->shop_id)->toBe($shop->getKey());
});

it('keeps an explicitly set item shop', function (): void {
    $shop = Shop::factory()->create();
    $other = Shop::factory()->create();
    $order = Order::factory()->create(['shop_id' => $shop->getKey()]);

    $item = Item::factory()->for($order)->withEurPrice('100')->create(['shop_id' => $other->getKey()]);

    expect($item->shop_id)->toBe($other->getKey());
});
