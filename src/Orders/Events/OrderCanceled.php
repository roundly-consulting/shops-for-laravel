<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Shops\Orders\Order;

final class OrderCanceled implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
    ) {}
}
