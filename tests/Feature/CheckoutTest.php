<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Facades\Shops;
use RoundlyConsulting\Shops\Orders\Exceptions\CheckoutRefusedException;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Products\ProductVariant;

/**
 * Checkout turns exactly what the cart showed into an order: each line's snapshotted name,
 * sku, price and tax class — never the catalog's values at checkout time.
 */
function checkoutCart(ProductVariant $variant, int $quantity = 1): Cart
{
    $cart = Cart::create(['currency' => 'EUR']);
    Shops::cart($cart)->add($variant, $quantity);

    return $cart;
}

it('charges the price the cart showed, not a later catalog price', function (): void {
    $variant = ProductVariant::factory()->withEurPrice('1000')->create(['stock' => 10, 'tax_class' => 'zero']);
    $cart = checkoutCart($variant, 2);

    expect((string) Shops::cart($cart)->price()->getFinalPrice())->toBe('20.00 EUR');

    // The admin reprices the variant after the customer saw the cart.
    $variant->update(['price' => Money::ofMinor(5000, 'EUR')]);

    $order = Shops::cart($cart)->checkout();

    expect((string) $order->price->getFinalPrice())->toBe('20.00 EUR')
        ->and($order->items->first()?->price->minor())->toBe('1000');
});

it('snapshots the line name, sku and tax class the cart holds', function (): void {
    $variant = ProductVariant::factory()->withEurPrice('1000')->create([
        'stock' => 10, 'sku' => 'OLD-SKU', 'name' => 'Old name', 'tax_class' => 'reduced',
    ]);
    $cart = checkoutCart($variant);

    $variant->update(['sku' => 'NEW-SKU', 'name' => 'New name', 'tax_class' => 'standard']);

    $item = Shops::cart($cart)->checkout()->items->firstOrFail();

    expect($item->sku)->toBe('OLD-SKU')
        ->and($item->name)->toBe('Old name')
        ->and($item->tax_class)->toBe('reduced')
        ->and($item->tax_rate)->toBe(1000);
});

it('places one order for a double-submitted checkout', function (): void {
    $variant = ProductVariant::factory()->withEurPrice('1000')->create(['stock' => 10]);
    $cart = checkoutCart($variant);

    // Two requests hold the same cart, lines loaded (a double-clicked "Place order").
    $first = Cart::query()->with('items')->findOrFail($cart->getKey());
    $second = Cart::query()->with('items')->findOrFail($cart->getKey());

    Shops::cart($first)->checkout();

    expect(fn () => Shops::cart($second)->checkout())
        ->toThrow(CheckoutRefusedException::class, 'empty');

    expect(Order::query()->count())->toBe(1)
        ->and($variant->refresh()->reserved)->toBe(1);
});

it('refuses to check out an empty cart', function (): void {
    $cart = Cart::create(['currency' => 'EUR']);

    expect(fn () => Shops::cart($cart)->checkout())->toThrow(CheckoutRefusedException::class);

    expect(Order::query()->count())->toBe(0);
});

it('locks the cart row before reading its lines', function (): void {
    $variant = ProductVariant::factory()->withEurPrice('1000')->create(['stock' => 10]);
    $cart = checkoutCart($variant);
    $baseline = DB::transactionLevel();

    $locks = recordLocks(function () use ($cart): void {
        Shops::cart($cart)->checkout();
    });

    expect($locks[0]['sql'])->toContain('carts')
        ->and($locks[0]['sql'])->not->toContain('cart_items')
        ->and($locks[0]['transactionDepth'])->toBe($baseline + 1);
});

it('refuses a line whose variant was soft-deleted, leaving the cart intact', function (): void {
    $kept = ProductVariant::factory()->withEurPrice('500')->create(['stock' => 10]);
    $gone = ProductVariant::factory()->withEurPrice('1000')->create(['stock' => 10]);
    $cart = checkoutCart($kept);
    Shops::cart($cart)->add($gone);

    $gone->delete();

    try {
        Shops::cart($cart)->checkout();
        $this->fail('Checkout should have been refused.');
    } catch (CheckoutRefusedException $e) {
        expect($e->cartItem?->product_variant_id)->toBe($gone->getKey());
    }

    expect(Order::query()->count())->toBe(0)
        ->and($cart->refresh()->items()->count())->toBe(2)
        ->and($kept->refresh()->reserved)->toBe(0);
});

it('refuses a line whose variant no longer exists', function (): void {
    $variant = ProductVariant::factory()->withEurPrice('1000')->create(['stock' => 10]);
    $cart = checkoutCart($variant);
    $cart->items()->firstOrFail()->update(['product_variant_id' => null]);

    expect(fn () => Shops::cart($cart)->checkout())->toThrow(CheckoutRefusedException::class);

    expect(Order::query()->count())->toBe(0);
});
