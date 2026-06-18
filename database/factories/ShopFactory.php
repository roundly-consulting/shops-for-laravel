<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Shops\Shops\Shop;

/**
 * @extends Factory<Shop>
 */
final class ShopFactory extends Factory
{
    protected $model = Shop::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'currency' => null,
        ];
    }

    public function currency(string $currency): static
    {
        return $this->state(fn (array $attributes): array => [
            'currency' => $currency,
        ]);
    }
}
