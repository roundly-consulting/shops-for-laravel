<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Orders\DataTransferObjects\Address;
use RoundlyConsulting\Shops\Orders\Order;

it('round-trips an address through the json cast', function (): void {
    $address = new Address(
        name: 'Ada Lovelace',
        street: '1 Analytical Way',
        city: 'London',
        postalCode: 'EC1',
        countryIso: 'GB',
        company: 'Babbage Co',
        phone: '+44 1234',
        email: 'ada@example.com',
    );

    $order = Order::factory()->create(['billing_address' => $address]);

    expect($order->refresh()->billing_address)
        ->toBeInstanceOf(Address::class)
        ->name->toBe('Ada Lovelace')
        ->city->toBe('London')
        ->postalCode->toBe('EC1')
        ->countryIso->toBe('GB')
        ->company->toBe('Babbage Co');
});

it('returns null for an unset address', function (): void {
    $order = Order::factory()->create();

    expect($order->refresh()->billing_address)->toBeNull();
});

it('builds an address from a partial array with defaults', function (): void {
    $address = Address::fromArray(['name' => 'Bob', 'city' => 'Paris']);

    expect($address->name)->toBe('Bob')
        ->and($address->street)->toBe('')
        ->and($address->company)->toBeNull();
});
