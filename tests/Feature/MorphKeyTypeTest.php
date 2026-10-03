<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * The `shops.key_type` seam. Three polymorphic columns point out at host models — an
 * order's `customer`, a cart's `owner`, and a stock adjustment's `reference` — and their id
 * columns must follow the host's key type or a uuid/ulid-keyed customer cannot place an
 * order on a strict engine. All three are nullable morphs; the shipped bigint schema stays
 * byte-identical.
 */
if (! function_exists('morphKtColumn')) {
    /**
     * @return array{type: string, type_name: string, nullable: bool|null}
     */
    function morphKtColumn(string $table, string $column): array
    {
        foreach (Schema::getColumns($table) as $c) {
            if ($c['name'] === $column) {
                return ['type' => $c['type'], 'type_name' => $c['type_name'], 'nullable' => $c['nullable']];
            }
        }

        return ['type' => 'MISSING', 'type_name' => 'MISSING', 'nullable' => null];
    }
}

$pgsqlOnly = fn (): bool => DriverMatrix::driver() !== 'pgsql';

/**
 * Rebuild the three morph tables under the current config. Child tables that FK into them
 * (order_items, cart_items) are dropped first so a strict engine allows the parent drop;
 * the assertions only need the morph parents back.
 */
function rebuildShopsMorphTables(): void
{
    foreach (['order_items', 'cart_items', 'orders', 'carts', 'stock_adjustments'] as $t) {
        Schema::dropIfExists($t);
    }

    (require __DIR__.'/../../database/migrations/0009_create_stock_adjustments_table.php')->up();
    (require __DIR__.'/../../database/migrations/0010_create_carts_table.php')->up();
    (require __DIR__.'/../../database/migrations/0012_create_orders_table.php')->up();
}

it('emits byte-identical morph columns on the bigint default', function (): void {
    // All three shop morphs are optional (nullable).
    Schema::dropIfExists('kt_ref');
    Schema::create('kt_ref', function (Blueprint $t): void {
        $t->id();
        $t->nullableMorphs('opt');
    });

    $opt = morphKtColumn('kt_ref', 'opt_id');
    $optType = morphKtColumn('kt_ref', 'opt_type');

    expect(morphKtColumn('orders', 'customer_id'))->toBe($opt)
        ->and(morphKtColumn('orders', 'customer_type'))->toBe($optType)
        ->and(morphKtColumn('carts', 'owner_id'))->toBe($opt)
        ->and(morphKtColumn('carts', 'owner_type'))->toBe($optType)
        ->and(morphKtColumn('stock_adjustments', 'reference_id'))->toBe($opt)
        ->and(morphKtColumn('stock_adjustments', 'reference_type'))->toBe($optType);

    Schema::dropIfExists('kt_ref');
});

it('renders each configured key type as a distinct real column type', function (string $keyType, string $expected): void {
    config()->set('shops.key_type', $keyType);

    rebuildShopsMorphTables();

    expect(morphKtColumn('orders', 'customer_id')['type'])->toBe($expected)
        ->and(morphKtColumn('carts', 'owner_id')['type'])->toBe($expected)
        ->and(morphKtColumn('stock_adjustments', 'reference_id')['type'])->toBe($expected)
        ->and(morphKtColumn('orders', 'customer_type')['type'])->toBe('character varying(255)');
})->with([
    'bigint' => ['bigint', 'bigint'],
    'uuid' => ['uuid', 'uuid'],
    'ulid' => ['ulid', 'character(26)'],
])->skip($pgsqlOnly, 'needs the postgres catalog to tell the key types apart — sqlite affinity hides it');

it('refuses to migrate on an unrecognized key type instead of falling back to bigint', function (): void {
    config()->set('shops.key_type', 'nonsense');

    // A typo in a host's config must stop the migration, never silently build bigint
    // columns for a uuid/ulid-keyed host.
    expect(function (): void {
        rebuildShopsMorphTables();
    })->toThrow(InvalidConfigurationException::class, 'Configuration value [shops.key_type] must be one of [bigint, uuid, ulid] (case-insensitive), [nonsense] given.');
});
