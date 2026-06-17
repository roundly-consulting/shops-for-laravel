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
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->paragraph(),
            'published_at' => fake()->optional()->dateTime(),
        ];
    }

    /**
     * Override the auto-created default variant's price after the product is
     * made.
     */
    public function withPrice(string $price, string $currency = 'EUR'): static
    {
        return $this->afterCreating(function (Product $product) use ($price, $currency): void {
            $product->defaultVariant?->update([
                'price' => Money::of($price, $currency),
                'currency' => $currency,
            ]);
        });
    }

    /**
     * Attach an explicit variant and drop the implicitly created default, so
     * the product ships only the variant(s) the caller asked for.
     */
    public function withVariant(ProductVariantFactory $variant): static
    {
        return $this->afterCreating(function (Product $product) use ($variant): void {
            $product->variants()->forceDelete();
            $variant->for($product)->create();
        });
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
