<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Shops\Products\ProductOption;
use RoundlyConsulting\Shops\Products\ProductOptionValue;

/**
 * @extends Factory<ProductOptionValue>
 */
final class ProductOptionValueFactory extends Factory
{
    protected $model = ProductOptionValue::class;

    public function definition(): array
    {
        return [
            'product_option_id' => ProductOption::factory(),
            'value' => fake()->unique()->word(),
            'position' => 0,
        ];
    }
}
