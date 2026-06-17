<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Shops\Products\Product;
use RoundlyConsulting\Shops\Products\ProductVariant;
use RoundlyConsulting\Shops\Support\Money\Money;

/**
 * @extends Factory<ProductVariant>
 */
final class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        $currency = fake()->randomElement(['EUR', 'USD']);

        return [
            'product_id' => Product::factory(),
            'sku' => mb_strtoupper(fake()->unique()->bothify('SKU-####-???')),
            'name' => fake()->words(2, true),
            'price' => Money::of(fake()->numberBetween(100, 5000), $currency),
            'currency' => $currency,
            'tax_class' => 'standard',
            'track_stock' => true,
            'stock' => fake()->numberBetween(0, 100),
            'reserved' => 0,
            'position' => 0,
        ];
    }

    public function withEurPrice(string $price): static
    {
        return $this->state(fn (array $attributes): array => [
            'price' => Money::EUR($price),
            'currency' => 'EUR',
        ]);
    }

    public function untracked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'track_stock' => false,
        ]);
    }

    public function withStock(int $stock): static
    {
        return $this->state(fn (array $attributes): array => [
            'stock' => $stock,
        ]);
    }
}
