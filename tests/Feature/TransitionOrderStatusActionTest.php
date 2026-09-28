<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Shops\Actions\Orders\TransitionOrderStatusAction;
use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Orders\Events\OrderCanceled;
use RoundlyConsulting\Shops\Orders\Events\OrderFulfilled;
use RoundlyConsulting\Shops\Orders\Events\OrderPaid;
use RoundlyConsulting\Shops\Orders\Events\OrderRefunded;
use RoundlyConsulting\Shops\Orders\Events\OrderStatusChanged;
use RoundlyConsulting\Shops\Orders\Exceptions\IllegalStatusTransitionException;
use RoundlyConsulting\Shops\Orders\Order;

it('transitions an order and stamps the matching timestamp column', function (): void {
    Carbon::setTestNow('2026-06-17 20:00:00');

    $order = Order::factory()->create();

    app(TransitionOrderStatusAction::class)->execute($order, Status::InProgress);

    expect($order->refresh())
        ->status->toBe(Status::InProgress)
        ->and($order->in_progress_at?->toDateTimeString())->toBe('2026-06-17 20:00:00');

    Carbon::setTestNow();
});

it('fires the generic and specific events on a legal transition', function (): void {
    Event::fake();

    $order = Order::factory()->inProgress()->create();

    app(TransitionOrderStatusAction::class)->execute($order, Status::Paid);

    Event::assertDispatched(OrderStatusChanged::class, function (OrderStatusChanged $event) use ($order): bool {
        return $event->order->is($order)
            && $event->from === Status::InProgress
            && $event->to === Status::Paid;
    });
    Event::assertDispatched(OrderPaid::class);
});

it('fires the fulfilled event', function (): void {
    Event::fake();

    $order = Order::factory()->paid()->create();
    app(TransitionOrderStatusAction::class)->execute($order, Status::Fulfilled);

    Event::assertDispatched(OrderFulfilled::class);
});

it('fires the canceled event', function (): void {
    Event::fake();

    $order = Order::factory()->create();
    app(TransitionOrderStatusAction::class)->execute($order, Status::Canceled);

    Event::assertDispatched(OrderCanceled::class);
});

it('fires the refunded event', function (): void {
    Event::fake();

    $order = Order::factory()->paid()->create();
    app(TransitionOrderStatusAction::class)->execute($order, Status::Refunded);

    Event::assertDispatched(OrderRefunded::class);
});

it('does not fire a specific event when entering InProgress', function (): void {
    Event::fake();

    $order = Order::factory()->create();
    app(TransitionOrderStatusAction::class)->execute($order, Status::InProgress);

    Event::assertDispatched(OrderStatusChanged::class);
    Event::assertNotDispatched(OrderPaid::class);
});

it('throws and changes nothing on an illegal transition', function (): void {
    Event::fake();

    $order = Order::factory()->create();

    expect(fn () => app(TransitionOrderStatusAction::class)->execute($order, Status::Fulfilled))
        ->toThrow(IllegalStatusTransitionException::class);

    expect($order->refresh())
        ->status->toBe(Status::New)
        ->and($order->fulfilled_at)->toBeNull();

    Event::assertNotDispatched(OrderStatusChanged::class);
});

it('forbids transitions out of a terminal status', function (): void {
    $order = Order::factory()->canceled()->create();

    expect(fn () => app(TransitionOrderStatusAction::class)->execute($order, Status::Paid))
        ->toThrow(IllegalStatusTransitionException::class);
});

it('refuses a transition decided on a stale copy of the order', function (): void {
    Event::fake([OrderRefunded::class]);

    $order = Order::factory()->paid()->create();

    // Two requests loaded the paid order; the first refunds it.
    $sameOrderElsewhere = Order::query()->findOrFail($order->getKey());
    $order->refund();

    // The second still sees "Paid" in memory, but the order is Refunded: refunding it again
    // would fire OrderRefunded twice (and credit the refund back twice).
    expect(fn () => $sameOrderElsewhere->refund())
        ->toThrow(IllegalStatusTransitionException::class)
        ->and($order->refresh()->status)->toBe(Status::Refunded);

    Event::assertDispatchedTimes(OrderRefunded::class, 1);
});
