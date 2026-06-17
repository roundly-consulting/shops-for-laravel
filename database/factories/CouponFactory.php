<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Shops\Discounts\Coupon;
use RoundlyConsulting\Shops\Discounts\Enums\DiscountType;

/**
 * @extends Factory<Coupon>
 */
final class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code' => mb_strtoupper(fake()->unique()->bothify('SAVE####')),
            'type' => DiscountType::Percentage,
            'value' => 10,
            'currency' => null,
            'max_usage' => null,
            'usage' => 0,
            'minimum_spend' => null,
            'starts_at' => null,
            'expires_at' => null,
        ];
    }

    public function percentage(int $percent): static
    {
        return $this->state(fn (): array => [
            'type' => DiscountType::Percentage,
            'value' => $percent,
        ]);
    }

    public function fixed(int $amount, string $currency = 'EUR'): static
    {
        return $this->state(fn (): array => [
            'type' => DiscountType::Fixed,
            'value' => $amount,
            'currency' => $currency,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->subDay(),
        ]);
    }

    public function notYetStarted(): static
    {
        return $this->state(fn (): array => [
            'starts_at' => now()->addDay(),
        ]);
    }

    public function exhausted(): static
    {
        return $this->state(fn (): array => [
            'max_usage' => 1,
            'usage' => 1,
        ]);
    }

    public function withMinimumSpend(int $amount, string $currency = 'EUR'): static
    {
        return $this->state(fn (): array => [
            'minimum_spend' => $amount,
            'currency' => $currency,
        ]);
    }
}
