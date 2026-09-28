<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Shops\Tests\Fixtures\SwappedShopTestCase;
use RoundlyConsulting\Shops\Tests\TestCase;
use RoundlyConsulting\Testing\Database\DriverMatrix;
use RoundlyConsulting\Testing\Fixtures\LockRecorder;
use RoundlyConsulting\Testing\Fixtures\LockRecordingGrammar;

uses(TestCase::class)->in('ArchTest.php', 'Feature', 'Unit');

// The model-swap proofs need `shops.shop_model` pointed at the host subclass BEFORE
// the providers boot, so they run on their own base case in their own directory —
// Pest binds a test case per directory, not per file.
uses(SwappedShopTestCase::class)->in('ModelSwap');

/**
 * Record every query that asked for a row lock, and the transaction depth it ran at,
 * through the shipped `LockRecorder` — on whichever engine the leg configured.
 *
 * `ProductVariant` is final and has no `*_model` config key, so the recorder's
 * variant A (the `RecordsLocks` model trait) is unavailable here and variant B is the
 * correct choice — exactly the "model is not subclassable" case it exists for.
 *
 * The two engines need opposite treatment, which is the whole reason this row adopts
 * `DriverMatrix`:
 *
 *  - **SQLite** has no row locks and compiles `lockForUpdate()` to an *empty string*,
 *    so the lock leaves no trace at all. `LockRecordingGrammar` compiles it to a
 *    trailing comment SQLite runs and ignores, making the lock observable.
 *  - **Postgres** emits a real `FOR UPDATE` that the engine actually enforces — no
 *    grammar needed, and swapping a SQLite grammar onto it would be nonsense. The lock
 *    is observed as the real thing it is.
 *
 * Both paths land in the same `LockRecorder`, so the assertions below are identical on
 * both legs — and on the pgsql leg they are pinning a lock the database really takes.
 *
 * @return list<array{marker: string, sql: string, transactionDepth: int}>
 */
function recordLocks(Closure $work): array
{
    $connection = DB::connection();

    LockRecorder::flush();

    if (DriverMatrix::driver() === 'sqlite') {
        $connection->setQueryGrammar(new LockRecordingGrammar($connection));

        LockRecorder::listenForMarkers();
    } else {
        DB::listen(static function (QueryExecuted $query): void {
            if (str_contains(strtolower($query->sql), 'for update')) {
                LockRecorder::record(
                    'lock-for-update',
                    $query->connection->transactionLevel(),
                    $query->sql,
                );
            }
        });
    }

    $work();

    return LockRecorder::recorded();
}
