<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('gives every owned table a shop_id and no shop_type column', function (string $table): void {
    expect(Schema::hasColumn($table, 'shop_id'))->toBeTrue()
        ->and(Schema::hasColumn($table, 'shop_type'))->toBeFalse();
})->with([
    'products',
    'product_categories',
    'coupons',
    'carts',
    'orders',
    'order_items',
    'tax_rates',
]);

it('does not add shop_id to the category_product pivot', function (): void {
    expect(Schema::hasColumn('category_product', 'shop_id'))->toBeFalse();
});

it('creates the tax_rates table with the expected columns', function (): void {
    foreach (['shop_id', 'name', 'tax_class', 'country', 'rate', 'is_default', 'priority'] as $column) {
        expect(Schema::hasColumn('tax_rates', $column))->toBeTrue();
    }
});

it('creates the shops table with the expected columns', function (): void {
    foreach (['name', 'slug', 'currency', 'deleted_at'] as $column) {
        expect(Schema::hasColumn('shops', $column))->toBeTrue();
    }
});
