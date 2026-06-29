<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use RoundlyConsulting\MediaLibrary\Exceptions\FileUnacceptableForBucket;
use RoundlyConsulting\Shops\Products\Product;

function featured(Product $product, string $name = 'hero.jpg'): void
{
    $product->addMedia(UploadedFile::fake()->image($name, 64, 64))
        ->toMediaBucket($product->featuredBucket());
}

function gallery(Product $product, string $name): void
{
    $product->addMedia(UploadedFile::fake()->image($name, 64, 64))
        ->toMediaBucket($product->galleryBucket());
}

it('keeps the featured bucket single-file', function (): void {
    $product = Product::factory()->create();

    featured($product, 'first.jpg');
    featured($product, 'second.jpg');

    expect($product->getMedia($product->featuredBucket()))->toHaveCount(1)
        ->and($product->featuredImage()?->file_name)->toContain('second');
});

it('collects multiple gallery images', function (): void {
    $product = Product::factory()->create();

    gallery($product, 'a.jpg');
    gallery($product, 'b.jpg');
    gallery($product, 'c.jpg');

    expect($product->galleryImages())->toHaveCount(3)
        ->and($product->galleryUrls())->toHaveCount(3);
});

it('uses the featured image for the seo image', function (): void {
    $product = Product::factory()->create();

    featured($product);
    gallery($product, 'g.jpg');

    expect($product->seoImageUrl())->toBe($product->featuredImageUrl());
});

it('falls back to the first gallery image for seo when no featured image', function (): void {
    $product = Product::factory()->create();

    gallery($product, 'only.jpg');

    expect($product->seoImageUrl())->toBe($product->galleryUrls()[0]);
});

it('returns empty readers for a product with no media', function (): void {
    $product = Product::factory()->create();

    expect($product->featuredImageUrl())->toBe('')
        ->and($product->galleryUrls())->toBe([])
        ->and($product->seoImageUrl())->toBe('');
});

it('rejects a non-image file for the featured bucket', function (): void {
    $product = Product::factory()->create();

    expect(fn () => $product->addMedia(UploadedFile::fake()->create('brief.pdf', 8, 'application/pdf'))
        ->toMediaBucket($product->featuredBucket()))
        ->toThrow(FileUnacceptableForBucket::class);
});

it('stores catalog media with public visibility', function (): void {
    $product = Product::factory()->create();

    featured($product);

    expect($product->featuredImage()?->visibility)->toBe('public');
});
