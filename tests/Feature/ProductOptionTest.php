<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Products\Product;
use RoundlyConsulting\Shops\Products\ProductOption;
use RoundlyConsulting\Shops\Products\ProductOptionValue;
use RoundlyConsulting\Shops\Products\ProductVariant;

it('exposes option relationships', function (): void {
    expect((new ProductOption)->product())->toBeInstanceOf(BelongsTo::class)
        ->and((new ProductOption)->values())->toBeInstanceOf(HasMany::class)
        ->and((new ProductOptionValue)->option())->toBeInstanceOf(BelongsTo::class)
        ->and((new ProductOptionValue)->variants())->toBeInstanceOf(BelongsToMany::class)
        ->and((new ProductVariant)->optionValues())->toBeInstanceOf(BelongsToMany::class);
});

it('resolves the variant for a set of option values', function (): void {
    $product = Product::factory()->create();

    $size = ProductOption::factory()->for($product)->create(['name' => 'Size']);
    $colour = ProductOption::factory()->for($product)->create(['name' => 'Colour']);

    $small = ProductOptionValue::factory()->for($size, 'option')->create(['value' => 'S']);
    $large = ProductOptionValue::factory()->for($size, 'option')->create(['value' => 'L']);
    $red = ProductOptionValue::factory()->for($colour, 'option')->create(['value' => 'Red']);

    $smallRed = $product->variants()->create([
        'sku' => 'S-RED', 'price' => Money::ofMinor('1000', 'EUR'), 'currency' => 'EUR',
    ]);
    $smallRed->optionValues()->attach([$small->id, $red->id]);

    $largeRed = $product->variants()->create([
        'sku' => 'L-RED', 'price' => Money::ofMinor('1200', 'EUR'), 'currency' => 'EUR',
    ]);
    $largeRed->optionValues()->attach([$large->id, $red->id]);

    expect($product->variantFor([$small->id, $red->id])?->sku)->toBe('S-RED')
        ->and($product->variantFor([$red->id, $large->id])?->sku)->toBe('L-RED')
        ->and($product->variantFor([$small->id]))->toBeNull();
});

it('cascades options when a product is deleted', function (): void {
    $product = Product::factory()->create();
    $option = ProductOption::factory()->for($product)->create();
    ProductOptionValue::factory()->for($option, 'option')->create();

    $product->forceDelete();

    expect(ProductOption::withTrashed()->count())->toBe(0)
        ->and(ProductOptionValue::withTrashed()->count())->toBe(0);
});
