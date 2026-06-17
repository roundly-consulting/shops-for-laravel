<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Enums;

enum Status: string
{
    case New = 'New';
    case InProgress = 'InProgress';
    case Completed = 'Completed';
    case Canceled = 'Canceled';

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
}
