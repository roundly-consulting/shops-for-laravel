<?php

declare(strict_types=1);

use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Contracts\PaymentGateway;
use RoundlyConsulting\Shops\Orders\Actions\ChargeOrderAction;
use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Orders\Item;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Payments\NullPaymentGateway;
use RoundlyConsulting\Shops\Payments\PaymentResult;

it('charges with the default null gateway and transitions to paid', function (): void {
    $order = Order::factory()->create();

    $result = app(ChargeOrderAction::class)->execute($order);

    expect($result)->toBeInstanceOf(PaymentResult::class)
        ->successful->toBeTrue()
        ->and($order->refresh()->status)->toBe(Status::Paid)
        ->and($order->paid_at)->not->toBeNull();
});

it('charges an already in-progress order without re-entering in-progress', function (): void {
    $order = Order::factory()->inProgress()->create();

    app(ChargeOrderAction::class)->execute($order);

    expect($order->refresh()->status)->toBe(Status::Paid);
});

it('leaves the status unchanged when the gateway fails', function (): void {
    app()->bind(PaymentGateway::class, fn (): PaymentGateway => new class implements PaymentGateway
    {
        public function charge(Order $order): PaymentResult
        {
            return PaymentResult::failure(Money::ofMinor(0, 'EUR'), 'declined');
        }

        public function refund(Order $order, Money $amount): PaymentResult
        {
            return PaymentResult::failure($amount);
        }
    });

    $order = Order::factory()->create();
    Item::factory()->for($order)->withEurPrice('1000')->create();

    $result = app(ChargeOrderAction::class)->execute($order);

    expect($result->successful)->toBeFalse()
        ->and($result->message)->toBe('declined')
        ->and($order->refresh()->status)->toBe(Status::New);
});

it('resolves a host-bound gateway from config', function (): void {
    config()->set('shops.payment.gateway', NullPaymentGateway::class);

    expect(app(PaymentGateway::class))->toBeInstanceOf(NullPaymentGateway::class);
});

it('refunds via the null gateway', function (): void {
    $order = Order::factory()->create();
    $gateway = new NullPaymentGateway;

    expect($gateway->refund($order, Money::ofMinor(500, 'EUR'))->amount->minor())->toBe('500');
});
