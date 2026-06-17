<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Concerns;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Shops\Orders\NumberGenerators\NumberGenerator;

/**
 * @phpstan-require-extends Model
 */
trait HasNumber
{
    protected function initializeHasNumber(): void
    {
        $generator = resolve(NumberGenerator::class);

        $this->setAttribute('number', $generator->generate($this));
    }
}
