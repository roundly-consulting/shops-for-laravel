<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Shops\ShopsServiceProvider;

/**
 * The package ships fourteen CREATEs wired together by nineteen real foreign keys
 * (shops → products → variants → options → carts → orders → line items). Publishing
 * preserves the source directory's order, so that order has to be runnable end to
 * end from an empty database: every table must exist before anything references it.
 *
 * SQLite happily creates a table referencing a missing parent — it only complains at
 * insert time — so these tests are the *committed* pin, not the proof. The order was
 * proved against a real PostgreSQL server, which rejects a dangling foreign key at
 * DDL time: the pre-fix directory order died on the second file ("relation \"carts\"
 * does not exist"), the fixed order applied all fourteen with all nineteen keys, and a
 * negative control (variants before products) was watched being rejected.
 *
 * These tests run the *published* files, under their published names, into a database
 * that starts empty — which is what a host actually does.
 */
beforeEach(function (): void {
    $this->publishedPath = sys_get_temp_dir().'/shops-migration-order-'.bin2hex(random_bytes(6));
    $this->publishedDatabase = $this->publishedPath.'/database.sqlite';

    File::makeDirectory($this->publishedPath, recursive: true);
    File::put($this->publishedDatabase, '');

    foreach (ServiceProvider::pathsToPublish(ShopsServiceProvider::class, 'shops-migrations') as $source => $target) {
        File::copy($source, $this->publishedPath.'/'.basename((string) $target));
    }

    config()->set('database.connections.published', [
        'driver' => 'sqlite',
        'database' => $this->publishedDatabase,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
});

afterEach(function (): void {
    File::deleteDirectory($this->publishedPath);
});

it('migrates the published files clean from an empty database', function (): void {
    $schema = Schema::connection('published');

    expect($schema->hasTable('shops'))->toBeFalse();

    $this->artisan('migrate', [
        '--database' => 'published',
        '--path' => $this->publishedPath,
        '--realpath' => true,
    ])->assertExitCode(0);

    $tables = [
        'shops', 'products', 'product_categories', 'category_product',
        'product_options', 'product_option_values', 'product_variants',
        'product_variant_option_value', 'stock_adjustments', 'carts',
        'cart_items', 'orders', 'order_items', 'tax_rates',
    ];

    foreach ($tables as $table) {
        expect($schema->hasTable($table))->toBeTrue();
    }
});

it('keeps every foreign key intact in the published schema', function (): void {
    $this->artisan('migrate', [
        '--database' => 'published',
        '--path' => $this->publishedPath,
        '--realpath' => true,
    ])->assertExitCode(0);

    $schema = Schema::connection('published');

    $foreignKeys = static fn (string $table): array => array_map(
        static fn (array $key): string => $key['columns'][0].' → '.$key['foreign_table'],
        $schema->getForeignKeys($table),
    );

    // The CREATE order is load-bearing, not incidental: each child really does
    // constrain onto a table created before it.
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

/**
 * The structural pin — and the one that would have caught the shipped bug on SQLite.
 *
 * Read every `constrained('parent')` out of the migration sources and assert the
 * parent's CREATE really does sort before the child's. This is engine-independent, so
 * it fails in CI (SQLite) the moment someone adds a table whose foreign key outruns
 * its target.
 */
it('creates every foreign key target before the table that references it', function (): void {
    $sources = glob(__DIR__.'/../../database/migrations/*.php');
    sort($sources);

    /** @var array<string, int> $createdAt */
    $createdAt = [];
    /** @var list<array{child: string, parent: string, at: int}> $edges */
    $edges = [];

    foreach ($sources as $position => $source) {
        $body = (string) file_get_contents($source);

        preg_match("/Schema::create\('([a-z_]+)'/", $body, $created);
        expect($created)->not->toBeEmpty();

        $createdAt[$created[1]] = $position;

        preg_match_all("/->constrained\('([a-z_]+)'\)/", $body, $parents);

        foreach ($parents[1] as $parent) {
            $edges[] = ['child' => $created[1], 'parent' => $parent, 'at' => $position];
        }
    }

    // The package really does emit the foreign keys this test is guarding.
    expect($edges)->toHaveCount(19);

    foreach ($edges as $edge) {
        expect($createdAt)->toHaveKey($edge['parent']);

        expect($createdAt[$edge['parent']])
            ->toBeLessThan(
                $edge['at'],
                "{$edge['child']} references {$edge['parent']}, which must be created first",
            );
    }
});
