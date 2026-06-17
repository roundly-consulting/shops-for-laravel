<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
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
