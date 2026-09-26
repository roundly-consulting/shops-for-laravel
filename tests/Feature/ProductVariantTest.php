<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use RoundlyConsulting\Money\Exceptions\CurrencyMismatch;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Products\Product;
use RoundlyConsulting\Shops\Products\ProductVariant;

it('belongs to a product', function (): void {
    expect((new ProductVariant)->product())->toBeInstanceOf(BelongsTo::class);
});

it('casts price to a money object', function (): void {
    $variant = ProductVariant::factory()->withEurPrice('1500')->create();

    expect($variant->price)
        ->toBeInstanceOf(Money::class)
        ->minor()->toBe('1500')
        ->currency()->code->toBe('EUR');
});

it('enforces sku uniqueness within a product', function (): void {
    $product = Product::factory()->create();
    ProductVariant::factory()->for($product)->create(['sku' => 'DUP']);

    expect(fn () => ProductVariant::factory()->for($product)->create(['sku' => 'DUP']))
        ->toThrow(QueryException::class);
});

it('allows the same sku across different products', function (): void {
    ProductVariant::factory()->for(Product::factory())->create(['sku' => 'SHARED']);
    ProductVariant::factory()->for(Product::factory())->create(['sku' => 'SHARED']);

    expect(ProductVariant::where('sku', 'SHARED')->count())->toBe(2);
});

it('computes available stock as stock minus reserved', function (): void {
    $variant = ProductVariant::factory()->create(['stock' => 10, 'reserved' => 3]);

    expect($variant->availableStock())->toBe(7);
});

it('reports in-stock based on available quantity', function (): void {
    $variant = ProductVariant::factory()->create(['stock' => 5, 'reserved' => 2, 'track_stock' => true]);

    expect($variant->inStock(3))->toBeTrue()
        ->and($variant->inStock(4))->toBeFalse();
});

it('treats untracked variants as always in stock', function (): void {
    $variant = ProductVariant::factory()->untracked()->create(['stock' => 0]);

    expect($variant->inStock(999))->toBeTrue();
});

it('scopes a query to in-stock variants', function (): void {
    $available = ProductVariant::factory()->create(['stock' => 5, 'reserved' => 0]);
    $depleted = ProductVariant::factory()->create(['stock' => 1, 'reserved' => 1]);
    $untracked = ProductVariant::factory()->untracked()->create(['stock' => 0]);

    $ids = ProductVariant::query()->inStock(2)->pluck('id');

    expect($ids)->toContain($available->id)
        ->and($ids)->toContain($untracked->id)
        ->and($ids)->not->toContain($depleted->id);
});

it('refuses to re-denominate a price without changing the currency first', function (): void {
    $variant = ProductVariant::factory()->withEurPrice('1500')->create();

    expect(fn () => $variant->update(['price' => Money::ofMinor(1500, 'USD')]))
        ->toThrow(CurrencyMismatch::class);
});

it('re-denominates a price when the currency is set first', function (): void {
    $variant = ProductVariant::factory()->withEurPrice('1500')->create();

    $variant->update(['currency' => 'USD', 'price' => Money::ofMinor(1700, 'USD')]);

    expect((string) $variant->refresh()->price)->toBe('17.00 USD');
});

it('round-trips a price beyond the old 32-bit column on every engine', function (): void {
    $variant = ProductVariant::factory()->create([
        'currency' => 'EUR',
        'price' => Money::ofMajor('99999999.99', 'EUR'),
    ]);

    expect((string) $variant->refresh()->price)->toBe('99999999.99 EUR');
});
