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
    /**
     * Give the order a number from the bound generator unless one was set explicitly.
     * Called at the insert point only — never on construction — so hydrating orders from
     * the database never runs the generator (the default one counts this year's orders).
     */
    protected function assignNumber(): void
    {
        $number = $this->getAttribute('number');

        if (is_string($number) && $number !== '') {
            return;
        }

        $this->setAttribute('number', resolve(NumberGenerator::class)->generate($this));
    }
}
