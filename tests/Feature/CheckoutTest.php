<?php

declare(strict_types=1);

use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Facades\Shops;
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
