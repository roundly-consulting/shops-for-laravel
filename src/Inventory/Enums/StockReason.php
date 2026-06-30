<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Inventory\Enums;

use RoundlyConsulting\Enums\Helpers;

enum StockReason: string
{
    use Helpers;

    case Received = 'Received';
    case Sold = 'Sold';
    case Reserved = 'Reserved';
    case Released = 'Released';
    case Returned = 'Returned';
    case Manual = 'Manual';

    /**
     * Whether this reason adjusts the reserved (held) quantity rather than the
     * on-hand stock.
     */
    public function affectsReserved(): bool
    {
        return $this === self::Reserved || $this === self::Released;
    }
}
