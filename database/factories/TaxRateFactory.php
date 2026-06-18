<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Shops\Shops\TaxRate;

/**
 * @extends Factory<TaxRate>
 */
final class TaxRateFactory extends Factory
{
    protected $model = TaxRate::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'tax_class' => 'standard',
            'country' => null,
            'rate' => 2000,
            'is_default' => false,
            'priority' => 0,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_default' => true,
        ]);
    }

    public function reduced(): static
    {
        return $this->state(fn (array $attributes): array => [
            'tax_class' => 'reduced',
            'rate' => 1000,
        ]);
    }

    public function forCountry(string $iso): static
    {
        return $this->state(fn (array $attributes): array => [
            'country' => mb_strtoupper($iso),
        ]);
    }
}
