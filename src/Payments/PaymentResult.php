<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Payments;

use RoundlyConsulting\Money\Money;

/**
 * The outcome of a payment gateway charge or refund.
 */
final readonly class PaymentResult
{
    public function __construct(
        public bool $successful,
        public Money $amount,
        public ?string $reference = null,
        public ?string $message = null,
    ) {}

    public static function success(Money $amount, ?string $reference = null): self
    {
        return new self(true, $amount, $reference);
    }

    public static function failure(Money $amount, ?string $message = null): self
    {
        return new self(false, $amount, null, $message);
    }
}
