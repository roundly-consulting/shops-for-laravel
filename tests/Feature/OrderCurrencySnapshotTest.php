<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Money\Exceptions\CurrencyMismatch;
use RoundlyConsulting\Money\Exceptions\InvalidMoneyValue;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Orders\Actions\AddOrderItemAction;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Products\ProductVariant;
use RoundlyConsulting\Shops\Shops\CurrentShop;
use RoundlyConsulting\Shops\Shops\Shop;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * An order remembers its own currency: changing SHOPS_DEFAULT_CURRENCY later never
 * re-denominates a placed order, and every amount is exact beyond int64 on a real engine.
 */
it('keeps a placed order in its currency after the configured default changes', function (): void {
    config()->set('shops.pricing.default_currency', 'USD');

    $order = Order::factory()->create();
    $variant = ProductVariant::factory()->create(['currency' => 'USD', 'price' => Money::ofMinor(1250, 'USD')]);
    app(AddOrderItemAction::class)->execute($order, $variant);

    config()->set('shops.pricing.default_currency', 'EUR');

    $fresh = Order::query()->findOrFail($order->id);

    expect($fresh->currency)->toBeInstanceOf(Currency::class)
        ->and($fresh->currency->code)->toBe('USD')
        ->and((string) $fresh->price->getFinalPrice())->toBe('12.50 USD');
});

it('takes the order currency from its shop', function (): void {
    $shop = Shop::factory()->currency('JPY')->create();

    $order = Order::factory()->create(['shop_id' => $shop->id]);

    expect($order->refresh()->currency->code)->toBe('JPY');
});

it('takes the order currency from the bound current shop', function (): void {
    $shop = Shop::factory()->currency('GBP')->create();

    $order = app(CurrentShop::class)->run($shop, fn (): Order => Order::factory()->create());

    expect($order->refresh()->currency->code)->toBe('GBP')
        ->and($order->shop_id)->toBe($shop->id);
});

it('keeps an explicitly set order currency', function (): void {
    $order = Order::factory()->create(['currency' => 'CHF']);

    expect($order->refresh()->currency->code)->toBe('CHF');
});

it('rejects an item in another currency than the order', function (): void {
    $order = Order::factory()->create(['currency' => 'EUR']);
    $variant = ProductVariant::factory()->create(['currency' => 'USD', 'price' => Money::ofMinor(100, 'USD')]);

    app(AddOrderItemAction::class)->execute($order, $variant);
})->throws(CurrencyMismatch::class);

it('round-trips a price beyond int64 and orders prices numerically on pgsql', function (): void {
    $wide = Money::ofMinor('100000000000000000000', 'EUR');

    foreach (['9', '10', '100000000000000000000'] as $minor) {
        ProductVariant::factory()->create(['currency' => 'EUR', 'price' => Money::ofMinor($minor, 'EUR'), 'sku' => 'P'.$minor]);
    }

    /** @var object{price: string|int} $row */
    $row = DB::table('product_variants')->where('sku', 'P100000000000000000000')->first(['price']);

    $ordered = ProductVariant::query()->whereIn('sku', ['P9', 'P10', 'P100000000000000000000'])->orderBy('price')->pluck('sku')->all();

    expect((string) $row->price)->toBe('100000000000000000000')
        ->and(ProductVariant::query()->where('sku', 'P100000000000000000000')->firstOrFail()->price->equals($wide))->toBeTrue()
        ->and($ordered)->toBe(['P9', 'P10', 'P100000000000000000000'])
        ->and(ProductVariant::query()->where('price', '>=', Money::ofMinor(10, 'EUR')->minor())->whereIn('sku', ['P9', 'P10', 'P100000000000000000000'])->count())->toBe(2);
})->skip(fn (): bool => DriverMatrix::driver() !== 'pgsql', 'needs an engine with exact decimal(38,0)');

it('refuses a price beyond int64 on sqlite instead of storing a float', function (): void {
    ProductVariant::factory()->create(['currency' => 'EUR', 'price' => Money::ofMinor('100000000000000000000', 'EUR')]);
})->throws(InvalidMoneyValue::class)->skip(fn (): bool => DriverMatrix::driver() !== 'sqlite', 'sqlite-only engine range guard');
