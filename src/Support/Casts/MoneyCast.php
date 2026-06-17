<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Support\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Shops\Support\Money\Money;

/**
 * Casts an integer price column plus its companion `currency` column into a
 * Money value object and back.
 *
 * @implements CastsAttributes<Money, Money>
 */
final class MoneyCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        $currency = $attributes['currency'] ?? 'EUR';

        return Money::of((int) $value, (string) $currency);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (! $value instanceof Money) {
            return [$key => $value];
        }

        return [
            $key => $value->getAmount(),
            'currency' => $value->getCurrency()->getCode(),
        ];
    }
}
