<?php

declare(strict_types=1);

use RoundlyConsulting\Credits\Facades\Credits;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Actions\Orders\ChargeOrderAction;
use RoundlyConsulting\Shops\Contracts\PaymentGateway;
use RoundlyConsulting\Shops\Facades\Shops;
use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Orders\Item;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Payments\PaymentResult;
use RoundlyConsulting\Shops\Tests\Fixtures\Customer;

/**
 * Store credit applied to an order is money already taken: the gateway must only charge
 * what is left, and an order credit pays in full is paid without a gateway charge at all.
 */
beforeEach(function (): void {
    config()->set('shops.payment.allow_store_credit', true);
});

function creditedOrder(int $credit, string $price = '1000'): Order
{
    $customer = Customer::create(['name' => 'Ada']);
    $customer->modifyCredits($credit, bucket: 'store_credit');

    $order = Order::factory()->create();
    $order->customer()->associate($customer)->save();
    Item::factory()->for($order)->withEurPrice($price)->state(['quantity' => 1, 'tax_class' => 'zero'])->create();

    return $order->refresh();
}

/**
 * A gateway that records every amount it is asked to charge.
 */
function recordingGateway(): PaymentGateway
{
    $gateway = new class implements PaymentGateway
    {
        /** @var list<string> */
        public array $charged = [];

        public function charge(Order $order): PaymentResult
        {
            $amount = $order->gatewayAmount();
            $this->charged[] = (string) $amount;

            return PaymentResult::success($amount, 'ch_1');
        }

        public function refund(Order $order, Money $amount): PaymentResult
        {
            return PaymentResult::success($amount);
        }
    };

    app()->instance(PaymentGateway::class, $gateway);

    return $gateway;
}

it('charges only the balance left after store credit with the default gateway', function (): void {
    $order = creditedOrder(600);

    $result = app(ChargeOrderAction::class)->execute($order);

    expect($result->successful)->toBeTrue()
        ->and((string) $result->amount)->toBe('4.00 EUR')
        ->and($order->refresh()->store_credit_applied?->minor())->toBe('600')
        ->and($order->status)->toBe(Status::Paid);
});

it('charges only the balance when credit was applied before the charge', function (): void {
    // Credit already on the order is not applied again; only the balance is charged.
    $order = creditedOrder(2000);
    $order->forceFill(['store_credit_applied' => Money::ofMinor(250, 'EUR')])->save();

    $result = app(ChargeOrderAction::class)->execute($order->refresh());

    expect((string) $result->amount)->toBe('7.50 EUR');
});

it('marks a fully credit-paid order paid without calling the gateway', function (): void {
    $gateway = recordingGateway();
    $order = creditedOrder(2000);

    $result = app(ChargeOrderAction::class)->execute($order);

    expect($gateway->charged)->toBe([])
        ->and($result->successful)->toBeTrue()
        ->and((string) $result->amount)->toBe('0.00 EUR')
        ->and($result->reference)->toBeNull()
        ->and($order->refresh()->status)->toBe(Status::Paid)
        ->and($order->paid_at)->not->toBeNull();
});

it('hands a host gateway the balance still owed', function (): void {
    $gateway = recordingGateway();
    $order = creditedOrder(300);

    app(ChargeOrderAction::class)->execute($order);

    expect($gateway->charged)->toBe(['7.00 EUR']);
});

it('reports the full final price as the gateway amount without store credit', function (): void {
    $order = creditedOrder(0);

    expect((string) $order->gatewayAmount())->toBe('10.00 EUR');
});

it('never reports a negative gateway amount', function (): void {
    $order = creditedOrder(0);
    $order->forceFill(['store_credit_applied' => Money::ofMinor(1500, 'EUR')])->save();

    expect((string) $order->refresh()->gatewayAmount())->toBe('0.00 EUR');
});

it('lets a credits fake record the store credit a charge debits', function (): void {
    $order = creditedOrder(credit: 400);
    $credits = Credits::fake();

    $result = Shops::order($order)->charge();

    $credits->assertDeducted($order->customer, 400, 'store_credit');

    expect($result->successful)->toBeTrue()
        ->and($order->refresh()->store_credit_applied?->minor())->toBe('400');
});
