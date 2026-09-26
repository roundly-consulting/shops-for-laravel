<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Shops\Orders\NumberGenerators\DefaultNumberGenerator;
use RoundlyConsulting\Shops\Orders\NumberGenerators\NumberGenerator;
use RoundlyConsulting\Shops\Orders\Order;

it('binds the default number generator', function (): void {
    expect(resolve(NumberGenerator::class))->toBeInstanceOf(DefaultNumberGenerator::class);
});

it('counts soft-deleted orders when generating a number', function (): void {
    Carbon::setTestNow('2023-06-01 10:00:00');

    Order::factory()->create()->delete();

    expect((new DefaultNumberGenerator)->generate(new Order))->toBe('23000002');

    Carbon::setTestNow();
});

/**
 * Numbering is an insert-time concern. The generator counts this year's orders, so running
 * it whenever an Order is constructed — including every row hydrated from the database —
 * turned loading N orders into N extra COUNT queries.
 */
it('runs no count query when loading orders', function (): void {
    Order::factory()->count(5)->create();

    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = mb_strtolower($query->sql);
    });

    $orders = Order::query()->get();
    Order::query()->findOrFail($orders->first()?->id);
    new Order;

    expect($orders)->toHaveCount(5)
        ->and($queries)->toHaveCount(2)
        ->and(array_filter($queries, static fn (string $sql): bool => str_contains($sql, 'count(')))->toBe([]);
});

it('leaves an unsaved order unnumbered', function (): void {
    expect((new Order)->number)->toBeNull()
        ->and(Order::factory()->make()->number)->toBeNull();
});

it('numbers an order on insert even with model events faked', function (): void {
    Carbon::setTestNow('2023-06-01 10:00:00');
    Event::fake();

    expect(Order::factory()->create()->number)->toBe('23000001')
        ->and(Order::factory()->create()->number)->toBe('23000002');

    Carbon::setTestNow();
});

it('keeps an explicitly set number and never renumbers on update', function (): void {
    $order = Order::factory()->create(['number' => 'CUSTOM-1']);

    $order->update(['note' => 'changed']);

    expect($order->refresh()->number)->toBe('CUSTOM-1');
});
