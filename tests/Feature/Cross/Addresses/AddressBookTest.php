<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Shops\Actions\Orders\PlaceOrderAction;
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\PlaceOrderData;
use RoundlyConsulting\Shops\Products\ProductVariant;
use RoundlyConsulting\Shops\Tests\Fixtures\Customer;

function shipping(Customer $customer): void
{
    $customer->addAddress(AddressData::make(
        city: 'London', street: '1 Shipping Way', postalCode: 'EC1', countryIso: 'GB',
        name: 'Ada Ship', type: AddressType::Shipping, isPrimary: true,
    ));
}

function billing(Customer $customer): void
{
    $customer->addAddress(AddressData::make(
        city: 'Paris', street: '2 Billing Rue', postalCode: '75001', countryIso: 'FR',
        name: 'Ada Bill', type: AddressType::Billing, isPrimary: true,
    ));
}

it('fills shipping and billing from the customer primaries', function (): void {
    $customer = Customer::create(['name' => 'Ada']);
    shipping($customer);
    billing($customer);

    $data = PlaceOrderData::fromAddressBook($customer);

    expect($data->shipping?->city)->toBe('London')
        ->and($data->shipping?->countryIso)->toBe('GB')
        ->and($data->billing?->city)->toBe('Paris')
        ->and($data->customer?->is($customer))->toBeTrue();
});

it('copies shipping to billing when no billing address exists', function (): void {
    $customer = Customer::create(['name' => 'Ada']);
    shipping($customer);

    $data = PlaceOrderData::fromAddressBook($customer);

    expect($data->billing?->city)->toBe('London')
        ->and($data->billing?->street)->toBe('1 Shipping Way');
});

it('does not copy shipping to billing when the toggle is off', function (): void {
    $customer = Customer::create(['name' => 'Ada']);
    shipping($customer);

    $data = PlaceOrderData::fromAddressBook($customer, billingSameAsShipping: false);

    expect($data->billing)->toBeNull()
        ->and($data->shipping?->city)->toBe('London');
});

it('yields null addresses when the customer has none', function (): void {
    $customer = Customer::create(['name' => 'Ada']);

    $data = PlaceOrderData::fromAddressBook($customer);

    expect($data->shipping)->toBeNull()
        ->and($data->billing)->toBeNull();
});

it('maps company, phone and email from the address meta bag', function (): void {
    $customer = Customer::create(['name' => 'Ada']);
    $customer->addAddress(AddressData::make(
        city: 'London', street: '1 Way', postalCode: 'EC1', countryIso: 'GB',
        name: 'Ada', type: AddressType::Shipping, isPrimary: true,
        meta: collect(['company' => 'Acme', 'phone' => '123', 'email' => 'a@b.test']),
    ));

    $data = PlaceOrderData::fromAddressBook($customer);

    expect($data->shipping?->company)->toBe('Acme')
        ->and($data->shipping?->phone)->toBe('123')
        ->and($data->shipping?->email)->toBe('a@b.test');
});

it('snapshots address-book data into a placed order', function (): void {
    $customer = Customer::create(['name' => 'Ada']);
    shipping($customer);
    billing($customer);

    $variant = ProductVariant::factory()->withEurPrice('1000')->create(['stock' => 5]);
    $cart = Cart::factory()->create();
    $cart->add($variant, 1);

    $order = app(PlaceOrderAction::class)->execute($cart, PlaceOrderData::fromAddressBook($customer));

    expect($order->shipping_address?->city)->toBe('London')
        ->and($order->billing_address?->city)->toBe('Paris')
        ->and($order->customer?->is($customer))->toBeTrue();
});
