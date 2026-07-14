<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Tests\Fixtures;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;

/**
 * Makes a pessimistic row lock *visible* on SQLite.
 *
 * Laravel's SQLiteGrammar compiles `lockForUpdate()` to an empty string — SQLite has
 * no row locks — so a lock leaves no trace in the query log and a test cannot tell a
 * locked read from an unlocked one. Emitting real `for update` isn't an option either
 * (SQLite rejects it as a syntax error).
 *
 * So compile the lock to a trailing SQL comment, which SQLite accepts and ignores.
 * The statement runs exactly as before, but `DB::listen()` can now see that the query
 * asked for a lock — and at which transaction depth it did so.
 */
final class LockRecordingGrammar extends SQLiteGrammar
{
    public const MARKER = '/* lock-for-update */';

    protected function compileLock(Builder $query, $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return $value ? self::MARKER : '';
    }
}
