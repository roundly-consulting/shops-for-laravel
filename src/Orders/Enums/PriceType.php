<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Enums;

use RoundlyConsulting\Shops\Orders\DataTransferObjects\Price;

/**
 * Whether catalog prices are stored tax-inclusive (gross) or tax-exclusive
 * (net). Drives how the {@see Price} DTO derives the tax portion of a line.
 */
enum PriceType: string
{
    case Net = 'net';
    case Gross = 'gross';
}
