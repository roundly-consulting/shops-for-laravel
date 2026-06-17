<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Support\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Address;

/**
 * Casts a JSON address column to an {@see Address} DTO and back.
 *
 * @implements CastsAttributes<Address, Address>
 */
final class AddressCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Address
    {
        if ($value === null) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $value, true) ?: [];

        return Address::fromArray($decoded);
    }

    /**
     * @param  Address|null  $value
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        return [$key => (string) json_encode($value->toArray())];
    }
}
