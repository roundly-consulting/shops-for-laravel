<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Shops\Shops\Shop;
use RoundlyConsulting\Shops\Support\ShopModel;

/**
 * @extends Factory<Shop>
 */
final class ShopFactory extends Factory
{
    protected $model = Shop::class;

    /**
     * Build the tenant the host configured in `shops.shop_model` when it extends Shop, so a
     * host subclass comes out of the factory with its own events, observers and table.
     *
     * @return class-string<Shop>
     */
    public function modelName(): string
    {
        return ShopModel::shop();
    }

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
