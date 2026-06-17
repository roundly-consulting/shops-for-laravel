<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RoundlyConsulting\Shops\Cart\Actions\AddToCart;
use RoundlyConsulting\Shops\Cart\Actions\ClearCart;
use RoundlyConsulting\Shops\Cart\Actions\RemoveFromCart;
use RoundlyConsulting\Shops\Cart\Actions\UpdateCartItem;
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Cart\CartItem;
use RoundlyConsulting\Shops\Products\ProductVariant;

beforeEach(function (): void {
    config()->set('shops.pricing.default_currency', 'EUR');
});

it('exposes cart item relationships', function (): void {
    expect((new CartItem)->cart())->toBeInstanceOf(BelongsTo::class)
        ->and((new CartItem)->variant())->toBeInstanceOf(BelongsTo::class);
});

it('adds to the cart via the action', function (): void {
    $cart = Cart::factory()->create();
    $variant = ProductVariant::factory()->withEurPrice('1000')->create();

    $item = app(AddToCart::class)->execute($cart, $variant, 2);

    expect($item->quantity)->toBe(2);
});

it('updates a cart item quantity', function (): void {
    $item = CartItem::factory()->create(['quantity' => 1]);

    $result = app(UpdateCartItem::class)->execute($item, 5);

    expect($result?->quantity)->toBe(5);
});

it('removes a cart item when its quantity drops to zero', function (): void {
    $item = CartItem::factory()->create(['quantity' => 1]);

    $result = app(UpdateCartItem::class)->execute($item, 0);

    expect($result)->toBeNull()
        ->and(CartItem::find($item->id))->toBeNull();
});

it('removes a cart item via the action', function (): void {
    $item = CartItem::factory()->create();

    app(RemoveFromCart::class)->execute($item);

    expect(CartItem::find($item->id))->toBeNull();
});

it('clears the cart', function (): void {
    $cart = Cart::factory()->create();
    CartItem::factory()->count(3)->for($cart)->create();

    app(ClearCart::class)->execute($cart);

    expect($cart->items()->count())->toBe(0);
});
