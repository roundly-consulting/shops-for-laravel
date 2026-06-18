<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Shops\Products\Category;
use RoundlyConsulting\Shops\Products\Product;
use RoundlyConsulting\Shops\Products\ProductVariant;
use RoundlyConsulting\Shops\Support\Money\Money;

it('has relationships', function (): void {
    $product = new Product;

    expect($product)
        ->shop()->toBeInstanceOf(BelongsTo::class)
        ->categories()->toBeInstanceOf(BelongsToMany::class)
        ->variants()->toBeInstanceOf(HasMany::class)
        ->defaultVariant()->toBeInstanceOf(HasOne::class)
        ->options()->toBeInstanceOf(HasMany::class);
});

it('auto-creates a single default variant on create', function (): void {
    $product = Product::factory()->create();

    expect($product->variants()->count())->toBe(1)
        ->and($product->defaultVariant)->toBeInstanceOf(ProductVariant::class);
});

it('does not auto-create a default when explicit variants are supplied', function (): void {
    $product = Product::factory()
        ->withVariant(ProductVariant::factory()->state(['sku' => 'EXPLICIT', 'position' => 1]))
        ->create();

    expect($product->variants()->count())->toBe(1)
        ->and($product->variants()->first()->sku)->toBe('EXPLICIT');
});

it('does not duplicate the default variant when re-ensured', function (): void {
    $product = Product::factory()->create();

    // Re-running the default-variant guard must be a no-op once a variant exists.
    (new ReflectionMethod($product, 'ensureDefaultVariant'))->invoke($product);

    expect($product->variants()->count())->toBe(1);
});

it('returns a zero price when it has no variant yet', function (): void {
    expect((new Product)->price)
        ->toBeInstanceOf(Money::class)
        ->getAmount()->toBe('0');
});

it('orders the default variant by position', function (): void {
    $product = Product::factory()->create();
    $product->variants()->create([
        'sku' => 'SECOND', 'price' => Money::EUR('100'), 'currency' => 'EUR', 'position' => -1,
    ]);

    expect($product->refresh()->defaultVariant->sku)->toBe('SECOND');
});

it('attaches categories', function (): void {
    $product = Product::factory()->create();
    $category = Category::factory()->create();

    $product->categories()->attach($category);

    expect($product->categories()->count())->toBe(1);
});

it('generates a slug from the name', function (): void {
    $product = Product::factory()->create(['name' => 'Very Long Name']);

    expect($product->slug)->toBe('very-long-name');
});

it('uses the slug as the route key', function (): void {
    expect((new Product)->getRouteKeyName())->toBe('slug');
});

it('proxies its price to the default variant', function (): void {
    $product = Product::factory()->withPrice('5000', 'EUR')->create();

    expect($product->refresh()->price)
        ->toBeInstanceOf(Money::class)
        ->getAmount()->toBe('5000')
        ->getCurrency()->getCode()->toBe('EUR');
});

it('casts published_at to carbon', function (): void {
    expect(Product::factory()->unpublished()->make()->published_at)->toBeNull()
        ->and(Product::factory()->published()->make()->published_at)->toBeInstanceOf(Carbon::class);
});
