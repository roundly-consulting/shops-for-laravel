<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders;

use RoundlyConsulting\Addresses\Address as AddressModel;
use RoundlyConsulting\Addresses\Contracts\Addressable;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Address;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\OrderAddresses;

/**
 * Builds an order's billing/shipping snapshot from a customer's saved addresses
 * (roundly-consulting/addresses-for-laravel). Shipping comes from the customer's
 * primary shipping address; billing from the primary billing address, falling
 * back to the shipping address when "billing same as shipping" is on and no
 * billing address exists. The shops order keeps snapshotting these as value
 * objects (no foreign key to the address book).
 *
 * Returned by `Shops::addresses()`.
 */
final class AddressBook
{
    /**
     * Resolve the customer's default shipping and billing addresses, mapped to
     * shops Address value objects. `$billingSameAsShipping` defaults to
     * `shops.addresses.billing_same_as_shipping`.
     */
    public function defaults(Addressable $customer, ?bool $billingSameAsShipping = null): OrderAddresses
    {
        $billingSameAsShipping ??= Config::boolean('shops.addresses.billing_same_as_shipping', true);

        $shipping = $this->map($customer->getPrimaryAddressOfType(AddressType::Shipping));
        $billing = $this->map($customer->getPrimaryAddressOfType(AddressType::Billing));

        if ($billing === null && $billingSameAsShipping) {
            $billing = $shipping;
        }

        return new OrderAddresses(billing: $billing, shipping: $shipping);
    }

    /**
     * Map an addresses-for-laravel Address model to the shops Address DTO. The
     * optional company/phone/email come from the address model's meta bag.
     */
    public function map(?AddressModel $address): ?Address
    {
        if ($address === null) {
            return null;
        }

        $meta = $address->meta;

        return new Address(
            name: (string) ($address->name ?? ''),
            street: (string) ($address->street ?? ''),
            city: (string) ($address->city ?? ''),
            postalCode: (string) ($address->postal_code ?? ''),
            countryIso: (string) ($address->country_iso ?? ''),
            company: $this->metaString($meta?->get('company')),
            phone: $this->metaString($meta?->get('phone')),
            email: $this->metaString($meta?->get('email')),
        );
    }

    private function metaString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
