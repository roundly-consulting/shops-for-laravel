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
     * The sign a stock delta for this reason must carry: +1 for reasons that add (received,
     * returned, reserved), -1 for reasons that remove (sold, released), 0 for a manual
     * correction, which may go either way. A zero delta is refused for every reason.
     */
    public function direction(): int
    {
        return match ($this) {
            self::Received, self::Returned, self::Reserved => 1,
            self::Sold, self::Released => -1,
            self::Manual => 0,
        };
    }

    /**
     * Whether this reason adjusts the reserved (held) quantity rather than the
     * on-hand stock.
     */
    public function affectsReserved(): bool
    {
        return $this === self::Reserved || $this === self::Released;
    }
}
