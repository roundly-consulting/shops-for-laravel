<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\DataTransferObjects;

final readonly class PlaceOrderData
{
    public function __construct(
        public ?Address $billing = null,
        public ?Address $shipping = null,
        public ?string $couponCode = null,
        public ?string $note = null,
    ) {}
}
