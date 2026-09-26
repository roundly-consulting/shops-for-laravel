<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Orders\NumberGenerators\DefaultNumberGenerator;
use RoundlyConsulting\Shops\Orders\NumberGenerators\NumberGenerator;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Testing\Database\DriverMatrix;

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

it('never hands out a number another order already holds', function (): void {
    Carbon::setTestNow('2023-06-01 10:00:00');

    // An order that took the next number in sequence explicitly (an import, a manual order).
    Order::factory()->create(['number' => '23000002']);

    $generated = Order::factory()->create();

    expect($generated->number)->not->toBe('23000002')
        ->and(Order::query()->where('number', $generated->number)->count())->toBe(1);

    Carbon::setTestNow();
});

it('does not reuse the number of a force-deleted order', function (): void {
    Carbon::setTestNow('2023-06-01 10:00:00');

    $first = Order::factory()->create();
    Order::factory()->create();
    $third = Order::factory()->create();
    $first->forceDelete();

    // Two orders are left, so a plain count would hand out the third's number again.
    expect(Order::factory()->create()->number)->not->toBe($third->number);

    Carbon::setTestNow();
});

it('lets no two orders share a number, with or without a shop', function (): void {
    Order::factory()->create(['number' => 'SAME-1']);

    expect(fn () => Order::factory()->create(['number' => 'SAME-1']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('numbers two orders placed at the same time differently', function (): void {
    Carbon::setTestNow('2023-06-01 10:00:00');
    config()->set('database.connections.probe', DriverMatrix::connectionConfig('pgsql'));
    DB::purge('probe');

    $resultFile = (string) tempnam(sys_get_temp_dir(), 'shops-number-');
    $pid = 0;
    $first = null;

    DB::transaction(function () use ($resultFile, &$pid, &$first): void {
        // The first order is inserted and numbered; its transaction is still open.
        $first = Order::factory()->create();

        $pid = pcntl_fork();

        if ($pid === 0) {
            // A second checkout on its own session, which cannot see the first order yet.
            $outcome = 'error';

            try {
                config()->set('database.connections.racer', DriverMatrix::connectionConfig('pgsql'));
                DB::setDefaultConnection('racer');
                // Inside a transaction, as PlaceOrderAction runs: the refused insert must not
                // abort it (Postgres would reject every later statement).
                $outcome = 'numbered '.DB::connection('racer')->transaction(
                    fn (): string => Order::on('racer')->create(['status' => Status::New])->number,
                );
            } catch (Throwable $e) {
                $outcome = 'error: '.$e->getMessage();
            }

            file_put_contents($resultFile, $outcome);
            posix_kill(posix_getpid(), SIGKILL);
        }

        // Hold the first transaction open until the racer has blocked on it (or finished).
        for ($i = 0; $i < 100; $i++) {
            clearstatcache();

            $waiting = DB::connection('probe')->selectOne(
                "select count(*) as n from pg_stat_activity where datname = current_database() and wait_event_type = 'Lock'",
            );

            if (filesize($resultFile) > 0 || (int) $waiting->n > 0) {
                break;
            }

            usleep(50_000);
        }
    });

    pcntl_waitpid($pid, $status);
    $outcome = (string) file_get_contents($resultFile);
    @unlink($resultFile);
    Carbon::setTestNow();

    expect($first?->number)->toBe('23000001')
        ->and($outcome)->toBe('numbered 23000002');
})->skip(fn (): bool => DriverMatrix::driver() !== 'pgsql' || ! function_exists('pcntl_fork'), 'needs a real engine and a second process');
