<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\DataTransferObjects\RatingSummary;
use RoundlyConsulting\Shops\Products\Product;
use RoundlyConsulting\Shops\Tests\Fixtures\Customer;

it('aggregates approved reviews into average and count', function (): void {
    $product = Product::factory()->create();

    foreach ([5, 4, 3] as $rating) {
        $product->addReview(Customer::create(['name' => 'R']))->rating($rating)->approved()->create();
    }

    expect($product->approvedReviewsCount())->toBe(3)
        ->and($product->averageRating())->toBe(4.0);
});

it('excludes a pending review from approved aggregates', function (): void {
    $product = Product::factory()->create();

    $product->addReview(Customer::create(['name' => 'A']))->rating(5)->approved()->create();
    $product->addReview(Customer::create(['name' => 'B']))->rating(1)->create();

    expect($product->approvedReviewsCount())->toBe(1)
        ->and($product->averageRating())->toBe(5.0);
});

it('reports a rating distribution', function (): void {
    $product = Product::factory()->create();

    $product->addReview(Customer::create(['name' => 'A']))->rating(5)->approved()->create();
    $product->addReview(Customer::create(['name' => 'B']))->rating(5)->approved()->create();
    $product->addReview(Customer::create(['name' => 'C']))->rating(2)->approved()->create();

    expect($product->ratingDistribution())->toMatchArray([5 => 2, 2 => 1]);
});

it('exposes a rating summary dto', function (): void {
    $product = Product::factory()->create();

    $product->addReview(Customer::create(['name' => 'A']))->rating(4)->approved()->create();

    $summary = $product->ratingSummary();

    expect($summary)->toBeInstanceOf(RatingSummary::class)
        ->and($summary->average)->toBe(4.0)
        ->and($summary->count)->toBe(1);
});
