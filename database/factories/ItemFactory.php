<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Orders\Item;
use RoundlyConsulting\Shops\Orders\Order;

/**
 * @extends Factory<Item>
 */
final class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        $currency = fake()->randomElement(['EUR', 'USD']);

        return [
            'name' => fake()->words(3, true),
            'sku' => mb_strtoupper(fake()->bothify('SKU-####')),
            'quantity' => fake()->numberBetween(1, 10),
            'currency' => $currency,
            'price' => Money::ofMinor(fake()->numberBetween(100, 5000), $currency),
            'tax_class' => 'standard',
            'order_id' => Order::factory(),
        ];
    }

    public function withUsdPrice(string $price): static
    {
        return $this->state(fn (array $attributes): array => [
            'currency' => 'USD',
            'price' => Money::ofMinor($price, 'USD'),
        ]);
    }

    public function withEurPrice(string $price): static
    {
        return $this->state(fn (array $attributes): array => [
            'currency' => 'EUR',
            'price' => Money::ofMinor($price, 'EUR'),
        ]);
    }
}
