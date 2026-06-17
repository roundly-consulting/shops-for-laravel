<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Shops\Products\Category;
use RoundlyConsulting\Shops\Products\Product;
use RoundlyConsulting\Shops\Support\Money\Money;

it('has relationships', function (): void {
    $product = new Product;

    expect($product)
        ->shop()->toBeInstanceOf(MorphTo::class)
        ->categories()->toBeInstanceOf(BelongsToMany::class);
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

it('casts price to a money object', function (): void {
    $eur = Product::factory()->withEurPrice('5000')->make();
    $usd = Product::factory()->withUsdPrice('2000')->make();

    expect($eur->price)
        ->toBeInstanceOf(Money::class)
        ->getAmount()->toBe('5000')
        ->getCurrency()->getCode()->toBe('EUR')
        ->and($usd->price->getCurrency()->getCode())->toBe('USD');
});

it('casts published_at to carbon', function (): void {
    expect(Product::factory()->unpublished()->make()->published_at)->toBeNull()
        ->and(Product::factory()->published()->make()->published_at)->toBeInstanceOf(Carbon::class);
});
