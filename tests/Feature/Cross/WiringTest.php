<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Shops\Products\Product;
use RoundlyConsulting\Shops\Tests\Fixtures\Customer;

it('boots every provider migration', function (string $table): void {
    expect(Schema::hasTable($table))->toBeTrue();
})->with([
    'media',
    'reviews',
    'review_votes',
    'attributes',
    'coupons',
    'coupon_redemptions',
    'credits',
    'addresses',
]);

it('round-trips a media row for an integer-keyed product', function (): void {
    $product = Product::factory()->create();

    $product->addMedia(UploadedFile::fake()->image('hero.jpg', 64, 64))
        ->toMediaBucket($product->featuredBucket());

    expect($product->getMedia($product->featuredBucket()))->toHaveCount(1)
        ->and($product->featuredImageUrl())->not->toBe('');
});

it('round-trips a review for an integer-keyed product', function (): void {
    $product = Product::factory()->create();
    $author = Customer::create(['name' => 'Ada']);

    $product->addReview($author)->rating(5)->approved()->create();

    expect($product->approvedReviewsCount())->toBe(1);
});

it('round-trips an attribute for an integer-keyed product', function (): void {
    $product = Product::factory()->create();

    $product->attachAttribute('material', 'wool');

    expect($product->attr('material')->string())->toBe('wool');
});
