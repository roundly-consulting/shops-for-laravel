<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Shops\Products\Product;
use RoundlyConsulting\Shops\Support\Money\Money;

/**
 * @extends Factory<Product>
 */
final class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $currency = fake()->randomElement(['EUR', 'USD']);

        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->paragraph(),
            'price' => Money::of(fake()->numberBetween(100, 5000), $currency),
            'currency' => $currency,
            'published_at' => fake()->optional()->dateTime(),
        ];
    }

    public function withUsdPrice(string $price): static
    {
        return $this->state(fn (array $attributes): array => [
            'price' => Money::USD($price),
            'currency' => 'USD',
        ]);
    }

    public function withEurPrice(string $price): static
    {
        return $this->state(fn (array $attributes): array => [
            'price' => Money::EUR($price),
            'currency' => 'EUR',
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'published_at' => fake()->dateTime(),
        ]);
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes): array => [
            'published_at' => null,
        ]);
    }
}
