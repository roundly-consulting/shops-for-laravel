<?php

declare(strict_types=1);

use RoundlyConsulting\Money\Exceptions\CurrencyMismatch;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Actions\Orders\AddOrderItemAction;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Products\Product;
use RoundlyConsulting\Shops\Products\ProductVariant;

it('relates an item back to its variant', function (): void {
    $variant = ProductVariant::factory()->withEurPrice('1000')->create();
    $order = Order::factory()->create();

    $item = app(AddOrderItemAction::class)->execute($order, $variant);

    expect($item->variant)->toBeInstanceOf(ProductVariant::class)
        ->and($item->variant->id)->toBe($variant->id);
});

it('snapshots variant data onto the order item', function (): void {
    $product = Product::factory()->create(['name' => 'Cap']);
    $variant = ProductVariant::factory()->for($product)->withEurPrice('2500')->create([
        'sku' => 'CAP-RED', 'name' => 'Red Cap', 'tax_class' => 'reduced',
    ]);
    $order = Order::factory()->create();

    $item = app(AddOrderItemAction::class)->execute($order, $variant, 3);

    expect($item)
        ->name->toBe('Red Cap')
        ->sku->toBe('CAP-RED')
        ->quantity->toBe(3)
        ->tax_class->toBe('reduced')
        ->and($item->price->minor())->toBe('2500')
        ->and($item->product_variant_id)->toBe($variant->id)
        ->and($item->product_id)->toBe($product->id);
});

it('falls back to the product name when the variant has none', function (): void {
    $product = Product::factory()->create(['name' => 'Mug']);
    $variant = ProductVariant::factory()->for($product)->withEurPrice('500')->create(['name' => null]);
    $order = Order::factory()->create();

    $item = app(AddOrderItemAction::class)->execute($order, $variant);

    expect($item->name)->toBe('Mug');
});

it('does not change the placed item when the variant price changes later', function (): void {
    $variant = ProductVariant::factory()->withEurPrice('1000')->create();
    $order = Order::factory()->create();

    $item = app(AddOrderItemAction::class)->execute($order, $variant);
    $variant->update(['price' => Money::ofMinor('9999', 'EUR')]);

    expect($item->refresh()->price->minor())->toBe('1000');
});

it('rejects a variant whose currency mismatches the order currency', function (): void {
    config()->set('shops.pricing.default_currency', 'EUR');
    $variant = ProductVariant::factory()->create([
        'price' => Money::ofMinor('1000', 'USD'),
        'currency' => 'USD',
    ]);
    $order = Order::factory()->create();

    expect(fn () => app(AddOrderItemAction::class)->execute($order, $variant))
        ->toThrow(CurrencyMismatch::class);
});
