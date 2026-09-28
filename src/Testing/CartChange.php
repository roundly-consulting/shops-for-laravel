<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Testing;

/**
 * What a recorded cart change did — narrows `Shops::assertCartChanged($cart, $change)`.
 */
enum CartChange: string
{
    case Added = 'added';
    case Updated = 'updated';
    case Removed = 'removed';
    case Cleared = 'cleared';
}
