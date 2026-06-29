<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Price;
use RoundlyConsulting\Shops\Products\ProductVariant;
use RoundlyConsulting\Shops\Support\Money\Money;

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
        ->and($item->price->getAmount())->toBe('1500');
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
        ->and($cart->subtotal()->getAmount())->toBe('2500');
});

it('applies a coupon to its price by code', function (): void {
    $cart = Cart::factory()->create();
    $cart->add(ProductVariant::factory()->withEurPrice('1000')->create(), 1);
    Coupon::factory()->percentage(10)->active()->create(['code' => 'SAVE10']);

    expect($cart->price('SAVE10'))->toBeInstanceOf(Price::class)
        ->and($cart->price('SAVE10')->getPriceAfterDiscount()->getAmount())->toBe('900');
});

it('uses the stored coupon code when none is passed', function (): void {
    $cart = Cart::factory()->create(['coupon_code' => 'SAVE10']);
    $cart->add(ProductVariant::factory()->withEurPrice('1000')->create(), 1);
    Coupon::factory()->percentage(10)->active()->create(['code' => 'SAVE10']);

    expect($cart->price()->getPriceAfterDiscount()->getAmount())->toBe('900');
});
