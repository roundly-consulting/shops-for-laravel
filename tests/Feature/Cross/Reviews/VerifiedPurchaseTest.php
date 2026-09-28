<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Shops\Actions\Orders\AddOrderItemAction;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Products\Product;
use RoundlyConsulting\Shops\Products\ProductVariant;
use RoundlyConsulting\Shops\Reviews\Contracts\VerifiedPurchaseResolver;
use RoundlyConsulting\Shops\Reviews\DatabaseVerifiedPurchaseResolver;
use RoundlyConsulting\Shops\Reviews\NullVerifiedPurchaseResolver;
use RoundlyConsulting\Shops\Tests\Fixtures\Customer;

it('leaves reviews unverified with the null resolver by default', function (): void {
    expect(app(VerifiedPurchaseResolver::class))->toBeInstanceOf(NullVerifiedPurchaseResolver::class);

    $product = Product::factory()->create();
    $review = $product->review(Customer::create(['name' => 'A']))->rating(5)->approved()->create();

    expect($review->verified)->toBeFalse();
});

it('marks a review verified when the resolver approves', function (): void {
    app()->bind(VerifiedPurchaseResolver::class, fn (): VerifiedPurchaseResolver => new class implements VerifiedPurchaseResolver
    {
        public function verified(Model $author, Product $product): bool
        {
            return true;
        }
    });

    $product = Product::factory()->create();
    $review = $product->review(Customer::create(['name' => 'A']))->rating(5)->approved()->create();

    expect($review->verified)->toBeTrue();
});

it('verifies a buyer with a paid and fulfilled order containing the product', function (): void {
    $customer = Customer::create(['name' => 'Ada']);
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->withEurPrice('1000')->create();

    $order = Order::factory()->fulfilled()->create();
    $order->customer()->associate($customer)->save();
    app(AddOrderItemAction::class)->execute($order, $variant, 1);

    expect((new DatabaseVerifiedPurchaseResolver)->verified($customer, $product))->toBeTrue();
});

it('does not verify a buyer without a matching paid order', function (): void {
    $customer = Customer::create(['name' => 'Grace']);
    $product = Product::factory()->create();

    expect((new DatabaseVerifiedPurchaseResolver)->verified($customer, $product))->toBeFalse();
});

it('does not verify when the order is paid but not fulfilled', function (): void {
    $customer = Customer::create(['name' => 'Ada']);
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->withEurPrice('1000')->create();

    $order = Order::factory()->paid()->create();
    $order->customer()->associate($customer)->save();
    app(AddOrderItemAction::class)->execute($order, $variant, 1);

    expect((new DatabaseVerifiedPurchaseResolver)->verified($customer, $product))->toBeFalse();
});
