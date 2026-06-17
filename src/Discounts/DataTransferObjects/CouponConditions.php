<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Discounts\DataTransferObjects;

use Carbon\CarbonInterface;
use RoundlyConsulting\Shops\Support\Money\Money;

/**
 * The conditions guarding whether a coupon may currently be applied.
 */
final readonly class CouponConditions
{
    public function __construct(
        public ?int $maxUsage = null,
        public int $usage = 0,
        public ?Money $minimumSpend = null,
        public ?CarbonInterface $startsAt = null,
        public ?CarbonInterface $expiresAt = null,
    ) {}

    public function passes(CarbonInterface $now, ?Money $spend = null): bool
    {
        if ($this->maxUsage !== null && $this->usage >= $this->maxUsage) {
            return false;
        }

        if ($this->startsAt !== null && $now->lessThan($this->startsAt)) {
            return false;
        }

        if ($this->expiresAt !== null && $now->greaterThan($this->expiresAt)) {
            return false;
        }

        if ($this->minimumSpend !== null && $spend !== null && $spend->compareTo($this->minimumSpend) < 0) {
            return false;
        }

        return true;
    }
}
