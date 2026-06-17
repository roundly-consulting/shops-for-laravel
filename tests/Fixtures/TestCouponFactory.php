<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestCoupon>
 */
final class TestCouponFactory extends Factory
{
    protected $model = TestCoupon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'value' => 10,
            'usage' => 0,
            'max_usage' => 5,
        ];
    }
}
