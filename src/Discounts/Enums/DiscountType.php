<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Discounts\Enums;

enum DiscountType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
}
