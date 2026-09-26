<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Contracts\ShippingMethod;
use RoundlyConsulting\Shops\Orders\Actions\QuoteShippingAction;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Address;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Shipping\FreeShippingMethod;

beforeEach(function (): void {
    config()->set('shops.pricing.default_currency', 'EUR');
});

it('quotes zero for free shipping', function (): void {
    $order = Order::factory()->create();
    $destination = new Address('Ada', '1 Way', 'London', 'EC1', 'GB');

    $quote = app(QuoteShippingAction::class)->execute($order, $destination);

    expect($quote->minor())->toBe('0')
        ->and($quote->currency()->code)->toBe('EUR');
});

it('exposes a label for the free shipping method', function (): void {
    expect((new FreeShippingMethod)->label())->toBe('Free shipping');
});

it('resolves the bound shipping method from config', function (): void {
    config()->set('shops.shipping.method', FreeShippingMethod::class);

    expect(app(ShippingMethod::class))->toBeInstanceOf(FreeShippingMethod::class);
});
