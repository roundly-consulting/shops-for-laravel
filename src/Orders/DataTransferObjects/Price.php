<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\DataTransferObjects;

use RoundlyConsulting\Shops\Contracts\Coupon;
use RoundlyConsulting\Shops\Support\Money\Money;

final readonly class Price
{
    public function __construct(
        public Money $price,
        public Money $shipping,
        public int $taxRate = 20,
        public ?Coupon $coupon = null,
    ) {}

    public function getTaxPrice(): Money
    {
        $price = $this->getPriceAfterDiscount()->add($this->shipping);

        return $price->multiply($this->taxRate)->divide(100);
    }

    public function getFinalPrice(): Money
    {
        return $this->getPriceAfterDiscount()
            ->add($this->shipping)
            ->add($this->getTaxPrice());
    }

    public function getDiscountValue(): Money
    {
        return $this->price->subtract($this->getPriceAfterDiscount());
    }

    public function getPriceAfterDiscount(): Money
    {
        return $this->coupon?->apply($this->price) ?? $this->price;
    }
}
