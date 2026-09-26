<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use RoundlyConsulting\Shops\Orders\NumberGenerators\NumberGenerator;

/**
 * @phpstan-require-extends Model
 */
trait HasNumber
{
    /** How often a generated number that lost an insert race is regenerated before giving up. */
    private const int NUMBER_ATTEMPTS = 5;

    /** Whether the number was generated here (and may be regenerated), not set by the caller. */
    private bool $numberGenerated = false;

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
        $this->numberGenerated = true;
    }

    /**
     * Insert, and when a *generated* number lost a race — another checkout inserted the same
     * one first and the unique index refused this one — ask the generator again, a bounded
     * number of times. Each attempt runs in a savepoint inside an outer transaction, so a
     * refused insert never poisons it. An explicitly set number is never replaced.
     *
     * @param  Builder<static>  $query
     * @param  array<string, mixed>  $attributes
     */
    protected function insertAndSetId(Builder $query, $attributes): void
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                $query->withSavepointIfNeeded(fn () => parent::insertAndSetId($query, $attributes));

                return;
            } catch (UniqueConstraintViolationException $e) {
                if (! $this->numberGenerated || $attempt >= self::NUMBER_ATTEMPTS) {
                    throw $e;
                }

                $this->setAttribute('number', resolve(NumberGenerator::class)->generate($this));
                $attributes['number'] = $this->getAttribute('number');
            }
        }
    }
}
