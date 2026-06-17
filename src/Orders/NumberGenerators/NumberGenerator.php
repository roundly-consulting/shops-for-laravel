<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\NumberGenerators;

use RoundlyConsulting\Shops\Orders\Order;

interface NumberGenerator
{
    public function generate(Order $order): string;
}
