<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\DataTransferObjects;

use RoundlyConsulting\Shops\Support\Casts\AddressCast;

/**
 * A postal address attached to an order's billing or shipping side. Stored as a
 * JSON column via {@see AddressCast}.
 */
final readonly class Address
{
    public function __construct(
        public string $name,
        public string $street,
        public string $city,
        public string $postalCode,
        public string $countryIso,
        public ?string $company = null,
        public ?string $phone = null,
        public ?string $email = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            street: (string) ($data['street'] ?? ''),
            city: (string) ($data['city'] ?? ''),
            postalCode: (string) ($data['postal_code'] ?? ''),
            countryIso: (string) ($data['country_iso'] ?? ''),
            company: isset($data['company']) ? (string) $data['company'] : null,
            phone: isset($data['phone']) ? (string) $data['phone'] : null,
            email: isset($data['email']) ? (string) $data['email'] : null,
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'street' => $this->street,
            'city' => $this->city,
            'postal_code' => $this->postalCode,
            'country_iso' => $this->countryIso,
            'company' => $this->company,
            'phone' => $this->phone,
            'email' => $this->email,
        ];
    }
}
