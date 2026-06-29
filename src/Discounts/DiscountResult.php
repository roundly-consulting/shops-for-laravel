<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Discounts;

use RoundlyConsulting\Shops\Support\Money\Money;

/**
 * The outcome of resolving a coupon code against a goods subtotal: the discount
 * amount to remove, whether the coupon grants free shipping, the code, and
 * whether a coupon was actually found.
 */
final readonly class DiscountResult
{
    public function __construct(
        public Money $discount,
        public bool $freeShipping = false,
        public ?string $code = null,
        public bool $found = false,
    ) {}

    /**
     * A no-discount result for an unknown or inapplicable code.
     */
    public static function none(string $currency, ?string $code = null): self
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
