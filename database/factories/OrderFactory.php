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
        return [
            'status' => Status::New,
            'note' => fake()->sentence(),
        ];
    }

    public function withStatus(Status $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
        ]);
    }

    public function inProgress(): static
    {
        return $this->withStatus(Status::InProgress)->state(fn (): array => [
            'in_progress_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->withStatus(Status::Paid)->state(fn (): array => [
            'in_progress_at' => now(),
            'paid_at' => now(),
        ]);
    }

    public function fulfilled(): static
    {
        return $this->withStatus(Status::Fulfilled)->state(fn (): array => [
            'in_progress_at' => now(),
            'paid_at' => now(),
            'fulfilled_at' => now(),
        ]);
    }

    public function canceled(): static
    {
        return $this->withStatus(Status::Canceled)->state(fn (): array => [
            'canceled_at' => now(),
        ]);
    }
}
