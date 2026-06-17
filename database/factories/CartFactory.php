<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use RoundlyConsulting\Shops\Cart\Cart;

/**
 * @extends Factory<Cart>
 */
final class CartFactory extends Factory
{
    protected $model = Cart::class;

    public function definition(): array
    {
        return [
            'token' => Str::uuid()->toString(),
            'currency' => 'EUR',
        ];
    }
}
