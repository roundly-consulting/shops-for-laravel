<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\DataTransferObjects;

/**
 * A customer's default billing and shipping addresses, as order snapshots — what
 * `Shops::addresses()->defaults($customer)` resolves. Either side is null when the customer
 * has no such address.
 */
final readonly class OrderAddresses
{
    public function __construct(
        public ?Address $billing = null,
        public ?Address $shipping = null,
    ) {}
}
