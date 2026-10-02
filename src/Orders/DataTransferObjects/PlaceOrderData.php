<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\DataTransferObjects;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Addresses\Contracts\Addressable;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\ShopsManager;

/**
 * What checkout needs besides the cart. `$shippingCost` is the shipping the customer chose (in
 * the cart's currency); leave it null to have the bound ShippingMethod quote the `$shipping`
 * address. With neither, the order carries no shipping charge.
 */
final readonly class PlaceOrderData
{
    public function __construct(
        public ?Address $billing = null,
        public ?Address $shipping = null,
        public ?string $couponCode = null,
        public ?string $note = null,
        public ?Model $customer = null,
        public ?Money $shippingCost = null,
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
        ?Money $shippingCost = null,
    ): self {
        $defaults = app(ShopsManager::class)->addresses()->defaults($customer, $billingSameAsShipping);

        return new self(
            billing: $defaults->billing,
            shipping: $defaults->shipping,
            couponCode: $couponCode,
            note: $note,
            customer: $customer,
            shippingCost: $shippingCost,
        );
    }
}
