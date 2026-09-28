<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\DataTransferObjects;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Addresses\Contracts\Addressable;
use RoundlyConsulting\Shops\ShopsManager;

final readonly class PlaceOrderData
{
    public function __construct(
        public ?Address $billing = null,
        public ?Address $shipping = null,
        public ?string $couponCode = null,
        public ?string $note = null,
        public ?Model $customer = null,
    ) {}

    /**
     * Build order data from a customer's saved addresses: shipping/billing are
     * filled from their primary addresses (with the "billing same as shipping"
     * rule), and the customer is linked to the resulting order.
     */
    public static function fromAddressBook(
        Model&Addressable $customer,
        ?string $couponCode = null,
        ?string $note = null,
        ?bool $billingSameAsShipping = null,
    ): self {
        $defaults = app(ShopsManager::class)->addresses()->defaults($customer, $billingSameAsShipping);

        return new self(
            billing: $defaults->billing,
            shipping: $defaults->shipping,
            couponCode: $couponCode,
            note: $note,
            customer: $customer,
        );
    }
}
