<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Money\Exceptions\CurrencyMismatch;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Actions\Cart\AddToCartAction;
use RoundlyConsulting\Shops\Actions\Cart\ClearCartAction;
use RoundlyConsulting\Shops\Actions\Cart\RemoveFromCartAction;
use RoundlyConsulting\Shops\Actions\Cart\UpdateCartItemAction;
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Cart\CartItem;
use RoundlyConsulting\Shops\Exceptions\ForeignItemException;
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
    $variant = ProductVariant::factory()->withEurPrice('1000')->create(['sku' => 'HAT', 'name' => 'Hat']);

    $item = app(AddToCartAction::class)->execute($cart, $variant, 2);

    expect($item->quantity)->toBe(2)
        ->and($item->sku)->toBe('HAT')
        ->and($item->name)->toBe('Hat')
        ->and($item->price->minor())->toBe('1000')
        ->and($item->cart_id)->toBe($cart->id);
});

it('merges a repeated variant into one line through the action', function (): void {
    $cart = Cart::factory()->create();
    $variant = ProductVariant::factory()->withEurPrice('1000')->create();

    app(AddToCartAction::class)->execute($cart, $variant, 1);
    $item = app(AddToCartAction::class)->execute($cart, $variant, 2);

    expect($item->quantity)->toBe(3)
        ->and($cart->items()->count())->toBe(1);
});

it('refuses a variant priced in another currency', function (): void {
    $cart = Cart::factory()->create();
    $variant = ProductVariant::factory()->create(['currency' => 'USD', 'price' => Money::ofMinor('1000', 'USD')]);

    expect(fn () => app(AddToCartAction::class)->execute($cart, $variant))->toThrow(CurrencyMismatch::class)
        ->and($cart->items()->count())->toBe(0);
});

/**
 * Two concurrent adds of the same variant (a double-clicked button) each read "no line
 * yet" and would each open a line. The cart row is locked for the read-then-write, inside
 * the action's own transaction.
 */
it('locks the cart row inside a transaction before merging a line', function (): void {
    $cart = Cart::factory()->create();
    $variant = ProductVariant::factory()->withEurPrice('1000')->create();
    $baseline = DB::transactionLevel();

    $locks = recordLocks(function () use ($cart, $variant): void {
        app(AddToCartAction::class)->execute($cart, $variant, 1);
    });

    expect($locks)->toHaveCount(1)
        ->and($locks[0]['sql'])->toContain('carts')
        ->and($locks[0]['transactionDepth'])->toBe($baseline + 1);
});

it('drops a loaded items relation so the next price read sees the change', function (): void {
    $cart = Cart::factory()->create();
    $variant = ProductVariant::factory()->withEurPrice('1000')->create();

    expect($cart->subtotal()->minor())->toBe('0');

    app(AddToCartAction::class)->execute($cart, $variant, 2);

    expect($cart->subtotal()->minor())->toBe('2000');

    $item = $cart->items()->sole();
    app(UpdateCartItemAction::class)->execute($cart, $item, 1);

    expect($cart->subtotal()->minor())->toBe('1000');

    app(RemoveFromCartAction::class)->execute($cart, $item);

    expect($cart->subtotal()->minor())->toBe('0');
});

it('updates a cart item quantity', function (): void {
    $item = CartItem::factory()->create(['quantity' => 1]);

    $result = app(UpdateCartItemAction::class)->execute($item->cart, $item, 5);

    expect($result?->quantity)->toBe(5);
});

it('removes a cart item when its quantity drops to zero', function (): void {
    $item = CartItem::factory()->create(['quantity' => 1]);

    $result = app(UpdateCartItemAction::class)->execute($item->cart, $item, 0);

    expect($result)->toBeNull()
        ->and(CartItem::find($item->id))->toBeNull();
});

it('removes a cart item via the action', function (): void {
    $item = CartItem::factory()->create();

    app(RemoveFromCartAction::class)->execute($item->cart, $item);

    expect(CartItem::find($item->id))->toBeNull();
});

it('refuses to update or remove a line of another cart', function (): void {
    $mine = Cart::factory()->create();
    $theirs = CartItem::factory()->create(['quantity' => 2]);

    expect(fn () => app(UpdateCartItemAction::class)->execute($mine, $theirs, 9))
        ->toThrow(ForeignItemException::class, "does not belong to cart [{$mine->id}]")
        ->and(fn () => app(UpdateCartItemAction::class)->execute($mine, $theirs, 0))->toThrow(ForeignItemException::class)
        ->and(fn () => app(RemoveFromCartAction::class)->execute($mine, $theirs))->toThrow(ForeignItemException::class)
        ->and($theirs->refresh()->quantity)->toBe(2)
        ->and($theirs->trashed())->toBeFalse();
});

it('clears the cart', function (): void {
    $cart = Cart::factory()->create();
    CartItem::factory()->count(3)->for($cart)->create();

    $cleared = app(ClearCartAction::class)->execute($cart);

    expect($cart->items()->count())->toBe(0)
        ->and($cleared->is($cart))->toBeTrue();
});
