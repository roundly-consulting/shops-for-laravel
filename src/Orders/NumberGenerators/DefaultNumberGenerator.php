<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\NumberGenerators;

use RoundlyConsulting\Shops\Orders\Order;

class DefaultNumberGenerator implements NumberGenerator
{
    public function generate(Order $order): string
    {
        $referenceSegment = $this->getNextNumber($order);
        $referenceSegment = str_pad((string) $referenceSegment, 6, '0', STR_PAD_LEFT);

        return now()->format('y').$referenceSegment;
    }

    protected function getNextNumber(Order $order): int
    {
        return $order->newQueryWithoutRelationships()
            ->withTrashed()
            ->whereYear('created_at', (string) now()->year)
            ->count() + 1;
    }
}
