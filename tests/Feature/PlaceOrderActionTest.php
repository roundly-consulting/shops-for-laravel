<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Discounts\Coupon;
use RoundlyConsulting\Shops\Inventory\Exceptions\InsufficientStockException;
use RoundlyConsulting\Shops\Orders\Actions\PlaceOrderAction;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Address;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\PlaceOrderData;
use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Orders\Events\OrderPlaced;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Products\Category;
use RoundlyConsulting\Shops\Products\ProductVariant;

beforeEach(function (): void {
    config()->set('shops.pricing.default_currency', 'EUR');
});

it('links a coupon by code and copies the cart shop', function (): void {
    $shop = Category::factory()->create();
    $coupon = Coupon::factory()->percentage(10)->create(['code' => 'WELCOME10']);

    $variant = ProductVariant::factory()->withEurPrice('1000')->create(['stock' => 10]);
    $cart = Cart::factory()->create(['coupon_code' => 'WELCOME10']);
    $cart->shop()->associate($shop)->save();
    $cart->add($variant, 1);

    $order = app(PlaceOrderAction::class)->execute($cart);

    expect($order->coupon_id)->toBe($coupon->id)
        ->and($order->shop_id)->toBe($shop->id)
        ->and($order->shop_type)->toBe($shop->getMorphClass());
});

it('skips coupon linking when no coupon model is configured', function (): void {
    config()->set('shops.discounts.coupon_model', null);
    config()->set('shops.orders.coupon_model', null);

    $variant = ProductVariant::factory()->withEurPrice('1000')->create(['stock' => 10]);
    $cart = Cart::factory()->create();
    $cart->add($variant, 1);

    $order = app(PlaceOrderAction::class)->execute($cart, new PlaceOrderData(couponCode: 'WHATEVER'));

    expect($order->coupon_id)->toBeNull();
});

it('ignores an unknown coupon code', function (): void {
    $variant = ProductVariant::factory()->withEurPrice('1000')->create(['stock' => 10]);
    $cart = Cart::factory()->create();
    $cart->add($variant, 1);

    $order = app(PlaceOrderAction::class)->execute($cart, new PlaceOrderData(couponCode: 'NOPE'));

    expect($order->coupon_id)->toBeNull();
});

it('places an order from a cart', function (): void {
    Event::fake([OrderPlaced::class]);

    $variant = ProductVariant::factory()->withEurPrice('1000')->create(['stock' => 10, 'sku' => 'X1']);
    $cart = Cart::factory()->create();
    $cart->add($variant, 2);

    $order = app(PlaceOrderAction::class)->execute($cart, new PlaceOrderData(
        billing: new Address('Ada', '1 Way', 'London', 'EC1', 'GB'),
    ));

    expect($order->status)->toBe(Status::New)
        ->and($order->number)->not->toBeEmpty()
        ->and($order->items()->count())->toBe(1)
        ->and($order->items()->first()->sku)->toBe('X1')
        ->and($order->billing_address?->name)->toBe('Ada');

    expect($variant->refresh()->reserved)->toBe(2);
    expect($cart->refresh()->items()->count())->toBe(0);

    Event::assertDispatched(OrderPlaced::class);
});

it('rolls back and leaves the cart intact on oversell', function (): void {
    $variant = ProductVariant::factory()->withEurPrice('1000')->create(['stock' => 1]);
    $cart = Cart::factory()->create();
    $cart->add($variant, 5);

    expect(fn () => app(PlaceOrderAction::class)->execute($cart))
        ->toThrow(InsufficientStockException::class);

    expect($cart->refresh()->items()->count())->toBe(1)
        ->and($variant->refresh()->reserved)->toBe(0)
        ->and(Order::count())->toBe(0);
});

it('skips cart lines without a variant', function (): void {
    $variant = ProductVariant::factory()->withEurPrice('1000')->create(['stock' => 10]);
    $cart = Cart::factory()->create();
    $cart->add($variant, 1);
    $cart->items()->first()->update(['product_variant_id' => null]);

    $order = app(PlaceOrderAction::class)->execute($cart);

    expect($order->items()->count())->toBe(0);
});

it('places an order with no addresses and the default data', function (): void {
    $variant = ProductVariant::factory()->withEurPrice('1000')->create(['stock' => 5]);
    $cart = Cart::factory()->create();
    $cart->add($variant, 1);

    $order = app(PlaceOrderAction::class)->execute($cart);

    expect($order->billing_address)->toBeNull()
        ->and($order->items()->count())->toBe(1);
});
