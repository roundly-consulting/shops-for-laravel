<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Exceptions\CurrencyMismatchException;
use RoundlyConsulting\Shops\Orders\Actions\AddOrderItemAction;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Products\Product;
use RoundlyConsulting\Shops\Products\ProductVariant;
use RoundlyConsulting\Shops\Support\Money\Money;

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
        ->and($item->price->getAmount())->toBe('2500')
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
    $variant->update(['price' => Money::EUR('9999')]);

    expect($item->refresh()->price->getAmount())->toBe('1000');
});

it('rejects a variant whose currency mismatches the order currency', function (): void {
    config()->set('shops.pricing.default_currency', 'EUR');
    $variant = ProductVariant::factory()->create([
        'price' => Money::USD('1000'),
        'currency' => 'USD',
    ]);
    $order = Order::factory()->create();

    expect(fn () => app(AddOrderItemAction::class)->execute($order, $variant))
        ->toThrow(CurrencyMismatchException::class);
});
