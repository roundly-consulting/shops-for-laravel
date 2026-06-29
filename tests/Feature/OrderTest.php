<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Price;
use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Orders\Exceptions\IllegalStatusTransitionException;
use RoundlyConsulting\Shops\Orders\Item;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Support\Money\Money;

it('has relationships', function (): void {
    $order = new Order;

    expect($order)
        ->shop()->toBeInstanceOf(BelongsTo::class)
        ->coupon()->toBeInstanceOf(BelongsTo::class)
        ->items()->toBeInstanceOf(HasMany::class);
});

it('calculates total order price from items respecting quantity', function (): void {
    $order = Order::factory()->create();

    Item::factory()->for($order)->withEurPrice('1000')->state(['quantity' => 2])->create();
    Item::factory()->for($order)->withEurPrice('300')->state(['quantity' => 1])->create();

    expect($order->refresh()->price)
        ->toBeInstanceOf(Price::class)
        ->and($order->price->getSubtotal())
        ->toBeInstanceOf(Money::class)
        ->getAmount()->toBe('2300')
        ->getCurrency()->getCode()->toBe('EUR');
});

it('returns a zero price for an order without items', function (): void {
    $order = Order::factory()->create();

    expect($order->price->getSubtotal()->getAmount())->toBe('0')
        ->and($order->price->getSubtotal()->getCurrency()->getCode())->toBe('EUR');
});

it('applies a coupon discount to the order price', function (): void {
    $coupon = Coupon::factory()->percentage(10)->active()->create(['code' => 'SAVE10']);
    $order = Order::factory()->create(['coupon_id' => $coupon->id]);

    Item::factory()->for($order)->withEurPrice('1000')->state(['quantity' => 1])->create();

    expect($order->refresh()->price->getPriceAfterDiscount()->getAmount())->toBe('900');
});

it('moves through its lifecycle via helper methods', function (): void {
    $order = Order::factory()->create();

    expect($order->markInProgress())->status->toBe(Status::InProgress)
        ->and($order->markPaid())->status->toBe(Status::Paid)
        ->and($order->markFulfilled())->status->toBe(Status::Fulfilled);

    expect($order->refresh())
        ->in_progress_at->not->toBeNull()
        ->paid_at->not->toBeNull()
        ->fulfilled_at->not->toBeNull();
});

it('cancels an order via the helper', function (): void {
    $order = Order::factory()->create();

    expect($order->cancel())->status->toBe(Status::Canceled)
        ->and($order->refresh()->canceled_at)->not->toBeNull();
});

it('refunds a paid order via the helper', function (): void {
    $order = Order::factory()->paid()->create();

    expect($order->refund())->status->toBe(Status::Refunded)
        ->and($order->refresh()->refunded_at)->not->toBeNull();
});

it('rejects an illegal transition through a helper', function (): void {
    $order = Order::factory()->create();

    expect(fn () => $order->refund())
        ->toThrow(IllegalStatusTransitionException::class);
});

it('generates an order number from the current year and order count', function (): void {
    Carbon::setTestNow('2023-12-15 10:00:00');

    expect(Order::factory()->create()->number)->toBe('23000001')
        ->and(Order::factory()->create()->number)->toBe('23000002');

    Carbon::setTestNow('2024-01-01 10:00:00');

    expect(Order::factory()->create()->number)->toBe('24000001');

    Carbon::setTestNow();
});
