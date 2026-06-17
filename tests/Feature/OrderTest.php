<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Price;
use RoundlyConsulting\Shops\Orders\Item;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Support\Money\Money;
use RoundlyConsulting\Shops\Tests\Fixtures\TestCoupon;

it('has relationships', function (): void {
    $order = new Order;

    expect($order)
        ->shop()->toBeInstanceOf(MorphTo::class)
        ->coupon()->toBeInstanceOf(BelongsTo::class)
        ->items()->toBeInstanceOf(HasMany::class);
});

it('calculates total order price from items', function (): void {
    $order = Order::factory()->create();

    Item::factory()->for($order)->withEurPrice('1000')->create();
    Item::factory()->for($order)->withEurPrice('300')->create();

    expect($order->refresh()->price)
        ->toBeInstanceOf(Price::class)
        ->and($order->price->price)
        ->toBeInstanceOf(Money::class)
        ->getAmount()->toBe('1300')
        ->getCurrency()->getCode()->toBe('EUR');
});

it('returns a zero price for an order without items', function (): void {
    $order = Order::factory()->create();

    expect($order->price->price->getAmount())->toBe('0');
});

it('applies a coupon discount to the order price', function (): void {
    $coupon = TestCoupon::factory()->create(['value' => 10]);
    $order = Order::factory()->create(['coupon_id' => $coupon->id]);

    Item::factory()->for($order)->withEurPrice('1000')->create();

    expect($order->refresh()->price->getPriceAfterDiscount()->getAmount())->toBe('900');
});

it('generates an order number from the current year and order count', function (): void {
    Carbon::setTestNow('2023-12-15 10:00:00');

    expect(Order::factory()->create()->number)->toBe('23000001')
        ->and(Order::factory()->create()->number)->toBe('23000002');

    Carbon::setTestNow('2024-01-01 10:00:00');

    expect(Order::factory()->create()->number)->toBe('24000001');

    Carbon::setTestNow();
});
