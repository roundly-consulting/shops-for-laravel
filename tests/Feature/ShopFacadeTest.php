<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Discounts\Coupon;
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

it('applies a coupon through the facade', function (): void {
    $coupon = Coupon::factory()->percentage(10)->create();

    expect(Shop::useCoupon($coupon, Money::EUR(1000))->getAmount())->toBe('900');
});
