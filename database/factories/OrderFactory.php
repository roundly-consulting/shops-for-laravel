<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Orders\Order;

/**
 * @extends Factory<Order>
 */
final class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $status = fake()->randomElement(Status::cases());

        return [
            'status' => $status,
            'note' => fake()->sentence(),
            'in_progress_at' => $status->isIn([Status::InProgress, Status::Completed]) ? fake()->dateTime() : null,
            'completed_at' => $status->is(Status::Completed) ? fake()->dateTime() : null,
            'canceled_at' => $status->is(Status::Canceled) ? fake()->dateTime() : null,
        ];
    }

    public function withStatus(Status $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
        ]);
    }
}
