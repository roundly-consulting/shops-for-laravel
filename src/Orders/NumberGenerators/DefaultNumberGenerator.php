<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\NumberGenerators;

use RoundlyConsulting\Shops\Orders\Order;

/**
 * `<two-digit year><6-digit sequence>`, e.g. `24000001`: this year's order count (soft-deleted
 * orders included) plus one, moved past any number already taken — by a force-deleted
 * order's successors or an explicitly numbered order — so it never repeats one.
 *
 * Two checkouts racing on the same count still compute the same candidate; the `orders.number`
 * unique index refuses the second insert and the order asks for a fresh number (see
 * {@see Order}), by which time the first is visible.
 */
class DefaultNumberGenerator implements NumberGenerator
{
    public function generate(Order $order): string
    {
        $sequence = $this->getNextNumber($order);

        while ($this->isTaken($order, $number = $this->format($sequence))) {
            $sequence++;
        }

        return $number;
    }

    protected function getNextNumber(Order $order): int
    {
        return $order->newQueryWithoutRelationships()
            ->withTrashed()
            ->whereYear('created_at', (string) now()->year)
            ->count() + 1;
    }

    protected function format(int $sequence): string
    {
        return now()->format('y').str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    protected function isTaken(Order $order, string $number): bool
    {
        return $order->newQueryWithoutRelationships()
            ->withTrashed()
            ->where('number', $number)
            ->exists();
    }
}
