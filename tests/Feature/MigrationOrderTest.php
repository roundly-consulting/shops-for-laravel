<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

/**
 * The package ships fourteen CREATEs wired together by nineteen real foreign keys
 * (shops → products → variants → options → carts → orders → line items). Publishing
 * preserves the source directory's order, so that order has to be runnable end to
 * end from an empty database: every table must exist before anything references it.
 *
 * Shops shipped that order broken once (#17). SQLite never noticed — it happily
 * creates a table referencing a missing parent and only complains at insert time —
 * so the structural pin below is what fails on *any* engine, and the Postgres pair
 * underneath it is what proves the pin against a database that really enforces the
 * constraint at DDL time.
 */
$migrations = __DIR__.'/../../database/migrations';

/**
 * The structural pin (M), and the one that would have caught #17 on SQLite: read
 * every foreign key out of the migration *source* and assert each parent's CREATE
 * sorts before the child that references it. `foreignKeys: 19` pins the edge count
 * so the check can never pass over an empty or mis-parsed directory.
 */
it('creates every foreign key target before the table that references it', function () use ($migrations): void {
    expect($migrations)->toHaveRunnableMigrationOrder(foreignKeys: 19);
});

/**
 * The real-engine proof (R). Postgres rejects a dangling foreign key at DDL time,
 * which is exactly the enforcement SQLite lacks: the pre-fix order died on the
 * second file ("relation \"carts\" does not exist"). `migrations: 14` pins the file
 * count so a relocated directory cannot pass vacuously.
 */
it('applies all fourteen migrations on postgres', function () use ($migrations): void {
    expect($migrations)->toApplyOnConnection('pgsql', migrations: 14);
})->skip(fn (): bool => ! test()->connectionAvailable('pgsql'), 'no postgres connection available');

/**
 * The negative control. A green FK test proves nothing until the engine has been
 * watched *rejecting* the broken order — otherwise the check is vacuous on any
 * driver that does not enforce foreign keys.
 */
it('rejects a child-before-parent order on postgres', function () use ($migrations): void {
    expect($migrations)->toRejectBrokenOrderOnConnection(
        fn (array $files): array => array_reverse($files),
        'pgsql',
    );
})->skip(fn (): bool => ! test()->connectionAvailable('pgsql'), 'no postgres connection available');

/**
 * The CREATE order is load-bearing, not incidental: each child really does
 * constrain onto a table created before it. This runs against whatever engine the
 * leg configured, so the edges are pinned on SQLite and re-pinned on Postgres.
 */
it('keeps every foreign key intact in the migrated schema', function (): void {
    $foreignKeys = static fn (string $table): array => array_map(
        static fn (array $key): string => $key['columns'][0].' → '.$key['foreign_table'],
        Schema::getForeignKeys($table),
    );

    expect($foreignKeys('products'))->toContain('shop_id → shops')
        ->and($foreignKeys('product_variants'))->toContain('product_id → products')
        ->and($foreignKeys('product_options'))->toContain('product_id → products')
        ->and($foreignKeys('product_option_values'))->toContain('product_option_id → product_options')
        ->and($foreignKeys('product_variant_option_value'))
        ->toContain('product_variant_id → product_variants')
        ->toContain('product_option_value_id → product_option_values')
        ->and($foreignKeys('category_product'))
        ->toContain('product_id → products')
        ->toContain('category_id → product_categories')
        ->and($foreignKeys('stock_adjustments'))->toContain('product_variant_id → product_variants')
        ->and($foreignKeys('cart_items'))
        ->toContain('cart_id → carts')
        ->toContain('product_variant_id → product_variants')
        ->and($foreignKeys('order_items'))
        ->toContain('order_id → orders')
        ->toContain('product_id → products')
        ->toContain('product_variant_id → product_variants')
        ->and($foreignKeys('tax_rates'))->toContain('shop_id → shops');
});
