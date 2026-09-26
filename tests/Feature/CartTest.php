<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Money\Exceptions\CurrencyMismatch;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Price;
use RoundlyConsulting\Shops\Products\ProductVariant;

beforeEach(function (): void {
    config()->set('shops.pricing.default_currency', 'EUR');
});

it('has relationships', function (): void {
    expect((new Cart)->items())->toBeInstanceOf(HasMany::class)
        ->and((new Cart)->owner())->toBeInstanceOf(MorphTo::class)
        ->and((new Cart)->shop())->toBeInstanceOf(BelongsTo::class);
});

it('reports whether it is a guest cart', function (): void {
    $guest = Cart::factory()->create();

    expect($guest->isGuest())->toBeTrue();
});

it('adds a variant as a snapshot item', function (): void {
    $cart = Cart::factory()->create();
    $variant = ProductVariant::factory()->withEurPrice('1500')->create(['sku' => 'ABC', 'name' => 'Hat']);

    $item = $cart->add($variant, 2);

    expect($item)
        ->name->toBe('Hat')
        ->sku->toBe('ABC')
        ->quantity->toBe(2)
        ->and($item->price->minor())->toBe('1500');
});

it('increments quantity when the same variant is added twice', function (): void {
    $cart = Cart::factory()->create();
    $variant = ProductVariant::factory()->withEurPrice('1000')->create();

    $cart->add($variant, 1);
    $cart->add($variant, 2);

    expect($cart->items()->count())->toBe(1)
        ->and($cart->items()->first()->quantity)->toBe(3);
});

it('falls back to the product name when the variant is unnamed', function (): void {
    $cart = Cart::factory()->create();
    $variant = ProductVariant::factory()->withEurPrice('1000')->create(['name' => null]);
    $variant->product->update(['name' => 'Mug']);

    expect($cart->add($variant)->name)->toBe('Mug');
});

it('computes the subtotal using the shared pricing engine', function (): void {
    $cart = Cart::factory()->create();
    $cart->add(ProductVariant::factory()->withEurPrice('1000')->create(), 2);
    $cart->add(ProductVariant::factory()->withEurPrice('500')->create(), 1);

    expect($cart->subtotal())->toBeInstanceOf(Money::class)
        ->and($cart->subtotal()->minor())->toBe('2500');
});

it('applies a coupon to its price by code', function (): void {
    $cart = Cart::factory()->create();
    $cart->add(ProductVariant::factory()->withEurPrice('1000')->create(), 1);
    Coupon::factory()->percentage(1000)->active()->create(['code' => 'SAVE10']);

    expect($cart->price('SAVE10'))->toBeInstanceOf(Price::class)
        ->and($cart->price('SAVE10')->getPriceAfterDiscount()->minor())->toBe('900');
});

it('uses the stored coupon code when none is passed', function (): void {
    $cart = Cart::factory()->create(['coupon_code' => 'SAVE10']);
    $cart->add(ProductVariant::factory()->withEurPrice('1000')->create(), 1);
    Coupon::factory()->percentage(1000)->active()->create(['code' => 'SAVE10']);

    expect($cart->price()->getPriceAfterDiscount()->minor())->toBe('900');
});

it('refuses a variant priced in another currency than the cart', function (): void {
    $cart = Cart::factory()->create(['currency' => 'USD']);
    $variant = ProductVariant::factory()->withEurPrice('1500')->create();

    expect(fn () => $cart->add($variant))->toThrow(CurrencyMismatch::class)
        ->and($cart->items()->count())->toBe(0);
});

it('stores the cart item in the variant currency, written by the price cast', function (): void {
    $cart = Cart::factory()->create(['currency' => 'EUR']);
    $variant = ProductVariant::factory()->withEurPrice('1500')->create();

    $item = $cart->add($variant);

    expect($cart->currency)->toBeInstanceOf(Currency::class)
        ->and($item->refresh()->currency)->toBe('EUR')
        ->and($item->price->equals($variant->price))->toBeTrue();
});
