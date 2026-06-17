<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Exceptions;

use RoundlyConsulting\Shops\Exceptions\ShopsException;
use RoundlyConsulting\Shops\Orders\Enums\Status;

final class IllegalStatusTransitionException extends ShopsException
{
    public static function between(Status $from, Status $to): self
    {
        return new self("Cannot transition an order from [{$from->value}] to [{$to->value}].");
    }
}
