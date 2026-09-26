<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Cart\CartItem;

/**
 * @extends Factory<CartItem>
 */
final class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    public function definition(): array
    {
        return [
            'cart_id' => Cart::factory(),
            'name' => fake()->words(2, true),
            'sku' => mb_strtoupper(fake()->bothify('SKU-####')),
            'quantity' => fake()->numberBetween(1, 5),
            'currency' => 'EUR',
            'price' => Money::ofMinor(fake()->numberBetween(100, 5000), 'EUR'),
            'tax_class' => 'standard',
        ];
    }
}
