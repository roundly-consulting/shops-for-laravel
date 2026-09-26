<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Discounts;

use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Money\Discounts\Discount;
use RoundlyConsulting\Money\Money;

/**
 * The outcome of resolving a coupon code against a goods subtotal: the discount
 * amount to remove, whether the coupon grants free shipping, the code, whether a
 * coupon was actually found, and — when one applies — the coupon as a money
 * {@see Discount} a host can compose with its own discounts in a DiscountStack.
 */
final readonly class DiscountResult
{
    public function __construct(
        public Money $discount,
        public bool $freeShipping = false,
        public ?string $code = null,
        public bool $found = false,
        public ?Discount $source = null,
    ) {}

    /**
     * A no-discount result for an unknown or inapplicable code.
     */
    public static function none(Currency|string $currency, ?string $code = null): self
    {
        return new self(
            discount: Money::zero($currency),
            freeShipping: false,
            code: $code,
            found: false,
        );
    }

    public function hasDiscount(): bool
    {
        return $this->discount->isPositive();
    }
}
