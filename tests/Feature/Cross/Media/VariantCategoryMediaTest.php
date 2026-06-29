<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use RoundlyConsulting\Shops\Products\Category;
use RoundlyConsulting\Shops\Products\Product;
use RoundlyConsulting\Shops\Products\ProductVariant;

it('stores a variant image independent of the product gallery', function (): void {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create();

    $product->addMedia(UploadedFile::fake()->image('product.jpg', 64, 64))
        ->toMediaBucket($product->galleryBucket());
    $variant->addMedia(UploadedFile::fake()->image('variant.jpg', 64, 64))
        ->toMediaBucket($variant->variantGalleryBucket());

    expect($variant->variantImages())->toHaveCount(1)
        ->and($variant->variantImageUrl())->not->toBe('')
        ->and($variant->variantImageUrls())->toHaveCount(1)
        ->and($product->galleryImages())->toHaveCount(1);
});

it('returns an empty variant image url when none set', function (): void {
    $variant = ProductVariant::factory()->create();

    expect($variant->variantImageUrl())->toBe('')
        ->and($variant->variantImageUrls())->toBe([]);
});

it('keeps the category banner single-file', function (): void {
    $category = Category::factory()->create();

    $category->addMedia(UploadedFile::fake()->image('one.jpg', 64, 64))->toMediaBucket($category->bannerBucket());
    $category->addMedia(UploadedFile::fake()->image('two.jpg', 64, 64))->toMediaBucket($category->bannerBucket());

    expect($category->getMedia($category->bannerBucket()))->toHaveCount(1)
        ->and($category->bannerUrl())->not->toBe('')
        ->and($category->banner()?->file_name)->toContain('two');
});

it('returns an empty banner url when none set', function (): void {
    $category = Category::factory()->create();

    expect($category->bannerUrl())->toBe('')
        ->and($category->banner())->toBeNull();
});
