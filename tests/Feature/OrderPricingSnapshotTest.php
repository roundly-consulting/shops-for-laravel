<?php

declare(strict_types=1);

use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Orders\Actions\AddOrderItemAction;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Address;
use RoundlyConsulting\Shops\Orders\Enums\PriceType;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Products\ProductVariant;
use RoundlyConsulting\Shops\Shops\Shop;
use RoundlyConsulting\Shops\Shops\TaxRate;

/**
 * What an order costs is fixed when its lines are added — like its item prices, currency and
 * coupon discount. The tax rate each line was taxed at and the catalog's price type are
 * snapshotted too, so editing a shop's tax rates, moving the shipping address or flipping
 * `shops.pricing.price_type` never re-prices a placed order: the charge, store credit and
 * refund credit-back all read `$order->price`.
 */
beforeEach(function (): void {
    config()->set('shops.pricing.default_currency', 'EUR');
    config()->set('shops.pricing.price_type', 'gross');
});

function placedOrderLine(Order $order, string $price, string $taxClass = 'standard'): void
{
    $variant = ProductVariant::factory()->withEurPrice($price)->create(['tax_class' => $taxClass]);

    app(AddOrderItemAction::class)->execute($order, $variant);
}

it('snapshots the resolved tax rate and its label onto the order item', function (): void {
    $shop = Shop::factory()->create();
    TaxRate::factory()->for($shop, 'shop')->default()->create(['rate' => 2000, 'name' => 'VAT 20 %']);

    $order = Order::factory()->create(['shop_id' => $shop->id]);
    placedOrderLine($order, '1200');

    $item = $order->items()->sole();

    expect($item->tax_rate)->toBe(2000)
        ->and($item->tax_label)->toBe('VAT 20 %')
        ->and($order->refresh()->price->taxSummary()->perRate()[0]->rate->label())->toBe('VAT 20 %');
});

it('keeps a placed order tax when the shop rate changes later', function (): void {
    $shop = Shop::factory()->create();
    $rate = TaxRate::factory()->for($shop, 'shop')->default()->create(['rate' => 2000]);

    $order = Order::factory()->create(['shop_id' => $shop->id]);
    placedOrderLine($order, '1200');

    $rate->update(['rate' => 1000]);

    expect($order->refresh()->price->getTaxPrice()->minor())->toBe('200')
        ->and($order->price->getNetPrice()->minor())->toBe('1000');
});

it('keeps a net order final price when the rate changes later', function (): void {
    config()->set('shops.pricing.price_type', 'net');

    $shop = Shop::factory()->create();
    $rate = TaxRate::factory()->for($shop, 'shop')->default()->create(['rate' => 2000]);

    $order = Order::factory()->create(['shop_id' => $shop->id]);
    placedOrderLine($order, '1000');

    $rate->update(['rate' => 1000]);

    // 1000 net + 20 % = 1200, as charged — not 1100 after the edit.
    expect($order->refresh()->price->getFinalPrice()->minor())->toBe('1200')
        ->and($order->gatewayAmount()->minor())->toBe('1200');
});

it('keeps the tax of the destination the order was placed for', function (): void {
    $shop = Shop::factory()->create();
    TaxRate::factory()->for($shop, 'shop')->default()->create(['rate' => 1900]);
    TaxRate::factory()->for($shop, 'shop')->forCountry('FR')->create(['rate' => 2000]);

    $order = Order::factory()->create([
        'shop_id' => $shop->id,
        'shipping_address' => new Address('Ada', '1 Way', 'Paris', '75001', 'FR'),
    ]);
    placedOrderLine($order, '1200');

    $order->update(['shipping_address' => new Address('Ada', '1 Way', 'Berlin', '10115', 'DE')]);

    expect($order->refresh()->price->getTaxPrice()->minor())->toBe('200');
});

it('keeps the price type the order was placed under', function (): void {
    $order = Order::factory()->create();
    placedOrderLine($order, '1200');

    config()->set('shops.pricing.price_type', 'net');

    // Placed as a gross catalog (20 % already inside the 1200); flipping the catalog to net
    // later must not add the tax on top of what the buyer was charged.
    expect($order->refresh()->price_type)->toBe(PriceType::Gross)
        ->and($order->price->getFinalPrice()->minor())->toBe('1200');
});

it('keeps resolving the rate live for an item added without a snapshot', function (): void {
    $shop = Shop::factory()->create();
    $rate = TaxRate::factory()->for($shop, 'shop')->default()->create(['rate' => 2000]);

    $order = Order::factory()->create(['shop_id' => $shop->id]);
    $order->items()->create([
        'name' => 'Imported line',
        'quantity' => 1,
        'price' => Money::ofMinor('1100', 'EUR'),
        'tax_class' => 'standard',
    ]);

    $rate->update(['rate' => 1000]);

    expect($order->refresh()->price->getTaxPrice()->minor())->toBe('100');
});
