<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use RoundlyConsulting\MediaLibrary\Exceptions\FileUnacceptableForBucket;
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

it('applies shops.media.max_file_size to every catalog bucket', function (string $model): void {
    config()->set('shops.media.max_file_size', 1024);

    $image = UploadedFile::fake()->image('big.jpg', 400, 400)->size(64); // 64 KB > 1 KB

    $upload = match ($model) {
        'product' => fn () => ($p = Product::factory()->create())->addMedia($image)->toMediaBucket($p->galleryBucket()),
        'variant' => fn () => ($v = ProductVariant::factory()->create())->addMedia($image)->toMediaBucket($v->variantGalleryBucket()),
        'category' => fn () => ($c = Category::factory()->create())->addMedia($image)->toMediaBucket($c->bannerBucket()),
    };

    expect($upload)->toThrow(FileUnacceptableForBucket::class);
})->with(['product', 'variant', 'category']);

it('falls back to the original image for a variant that is not generated', function (): void {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create();
    $category = Category::factory()->create();

    $product->addMedia(UploadedFile::fake()->image('p.jpg', 64, 64))->toMediaBucket($product->featuredBucket());
    $product->addMedia(UploadedFile::fake()->image('g.jpg', 64, 64))->toMediaBucket($product->galleryBucket());
    $variant->addMedia(UploadedFile::fake()->image('v.jpg', 64, 64))->toMediaBucket($variant->variantGalleryBucket());
    $category->addMedia(UploadedFile::fake()->image('c.jpg', 64, 64))->toMediaBucket($category->bannerBucket());

    // A 64 px image yields no responsive-1600 (no upscaling): every reader serves the original.
    expect($product->featuredImageUrl('responsive-1600'))->toBe($product->featuredImageUrl())
        ->and($product->galleryUrls('responsive-1600'))->toBe($product->galleryUrls())
        ->and($product->seoImageUrl('responsive-1600'))->toBe($product->featuredImageUrl())
        ->and($variant->variantImageUrl('responsive-1600'))->toBe($variant->variantImageUrl())
        ->and($variant->variantImageUrls('responsive-1600'))->toBe($variant->variantImageUrls())
        ->and($category->bannerUrl('responsive-1600'))->toBe($category->bannerUrl());
});
