<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Orders\DataTransferObjects\Address;
use RoundlyConsulting\Shops\Orders\Item;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Shops\Shop;
use RoundlyConsulting\Shops\Shops\TaxRate;
use RoundlyConsulting\Shops\Tests\Fixtures\TestCoupon;

beforeEach(function (): void {
    config()->set('shops.pricing.default_currency', 'EUR');
    config()->set('shops.pricing.price_type', 'gross');
});

it('computes order tax from the shops own database rate', function (): void {
    $shop = Shop::factory()->create();
    TaxRate::factory()->for($shop, 'shop')->default()->create([
        'tax_class' => 'standard', 'rate' => 1900,
    ]);

    $order = Order::factory()->create(['shop_id' => $shop->id]);
    Item::factory()->for($order)->withEurPrice('1190')->state(['quantity' => 1])->create();

    expect($order->refresh()->price->getTaxPrice()->getAmount())->toBe('190')
        ->and($order->price->getNetPrice()->getAmount())->toBe('1000');
});

it('falls back to the config floor for an order without a shop', function (): void {
    $order = Order::factory()->create(['shop_id' => null]);
    Item::factory()->for($order)->withEurPrice('1200')->state(['quantity' => 1])->create();

    expect($order->refresh()->price->getTaxPrice()->getAmount())->toBe('200');
});

it('prefers a country-specific rate from the orders shipping address', function (): void {
    $shop = Shop::factory()->create();
    TaxRate::factory()->for($shop, 'shop')->default()->create([
        'tax_class' => 'standard', 'rate' => 1900,
    ]);
    TaxRate::factory()->for($shop, 'shop')->forCountry('FR')->create([
        'tax_class' => 'standard', 'rate' => 2000,
    ]);

    $order = Order::factory()->create([
        'shop_id' => $shop->id,
        'shipping_address' => new Address('Ada', '1 Way', 'Paris', '75001', 'FR'),
    ]);
    Item::factory()->for($order)->withEurPrice('1200')->state(['quantity' => 1])->create();

    expect($order->refresh()->price->getTaxPrice()->getAmount())->toBe('200');
});

it('scales tax proportionally with a coupon after the basis-point change', function (): void {
    config()->set('shops.pricing.price_type', 'net');

    $shop = Shop::factory()->create();
    TaxRate::factory()->for($shop, 'shop')->default()->create([
        'tax_class' => 'standard', 'rate' => 2000,
    ]);

    $coupon = TestCoupon::factory()->create(['value' => 10]);
    $order = Order::factory()->create(['shop_id' => $shop->id, 'coupon_id' => $coupon->id]);
    Item::factory()->for($order)->withEurPrice('1000')->state(['quantity' => 1])->create();

    expect($order->refresh()->price->getTaxPrice()->getAmount())->toBe('180');
});
