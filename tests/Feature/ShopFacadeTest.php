<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Facades\Shop;
use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Payments\PaymentResult;
use RoundlyConsulting\Shops\Products\ProductVariant;
use RoundlyConsulting\Shops\ShopManager;
use RoundlyConsulting\Shops\Support\Money\Money;

beforeEach(function (): void {
    config()->set('shops.pricing.default_currency', 'EUR');
});

it('resolves the manager as a singleton', function (): void {
    expect(app(ShopManager::class))->toBe(app(ShopManager::class));
});

it('places an order through the facade', function (): void {
    $variant = ProductVariant::factory()->withEurPrice('1000')->create(['stock' => 5]);
    $cart = Cart::factory()->create();
    $cart->add($variant, 1);

    $order = Shop::placeOrder($cart);

    expect($order)->toBeInstanceOf(Order::class)
        ->and($order->items()->count())->toBe(1);
});

it('transitions an order through the facade', function (): void {
    $order = Order::factory()->create();

    expect(Shop::transition($order, Status::InProgress)->status)->toBe(Status::InProgress);
});

it('charges an order through the facade', function (): void {
    $order = Order::factory()->create();

    expect(Shop::charge($order))->toBeInstanceOf(PaymentResult::class)
        ->and($order->refresh()->status)->toBe(Status::Paid);
});

it('previews a coupon discount through the facade', function (): void {
    Coupon::factory()->percentage(10)->active()->create(['code' => 'SAVE10']);

    $result = Shop::discountFor('SAVE10', Money::EUR(1000));

    expect($result->discount->getAmount())->toBe('100')
        ->and($result->found)->toBeTrue();
});
