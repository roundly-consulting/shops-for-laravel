<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Shops\Products\Category;

it('has a shop relationship', function (): void {
    expect((new Category)->shop())->toBeInstanceOf(MorphTo::class);
});

it('generates a slug from the name', function (): void {
    $category = Category::factory()->create(['name' => 'Very Long Name']);

    expect($category->slug)->toBe('very-long-name');
});

it('keeps an explicitly provided slug', function (): void {
    $category = Category::factory()->create(['name' => 'Drinks', 'slug' => 'custom-slug']);

    expect($category->slug)->toBe('custom-slug');
});

it('uses the slug as the route key', function (): void {
    expect((new Category)->getRouteKeyName())->toBe('slug');
});

it('casts published_at to carbon', function (): void {
    expect(Category::factory()->unpublished()->make()->published_at)->toBeNull()
        ->and(Category::factory()->published()->make()->published_at)->toBeInstanceOf(Carbon::class);
});
