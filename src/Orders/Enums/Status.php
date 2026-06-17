<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Enums;

enum Status: string
{
    case New = 'New';
    case InProgress = 'InProgress';
    case Paid = 'Paid';
    case Fulfilled = 'Fulfilled';
    case Canceled = 'Canceled';
    case Refunded = 'Refunded';

    public function is(self $status): bool
    {
        return $this === $status;
    }

    /**
     * @param  array<int, self>  $statuses
     */
    public function isIn(array $statuses): bool
    {
        return in_array($this, $statuses, true);
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::InProgress, self::Canceled],
            self::InProgress => [self::Paid, self::Canceled],
            self::Paid => [self::Fulfilled, self::Refunded],
            self::Fulfilled => [self::Refunded],
            self::Canceled, self::Refunded => [],
        };
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * The order timestamp column stamped when an order enters this status.
     */
    public function timestampColumn(): ?string
    {
        return match ($this) {
            self::InProgress => 'in_progress_at',
            self::Paid => 'paid_at',
            self::Fulfilled => 'fulfilled_at',
            self::Canceled => 'canceled_at',
            self::Refunded => 'refunded_at',
            self::New => null,
        };
    }
}
