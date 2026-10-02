<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Contracts\PaymentGateway;
use RoundlyConsulting\Shops\Facades\Shops;
use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Orders\Item;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Payments\PaymentResult;
use RoundlyConsulting\Shops\Payments\StoreCreditTender;
use RoundlyConsulting\Shops\Tests\Fixtures\Customer;

/**
 * Store credit an order never got to keep is the buyer's money: a declined charge must not
 * keep the credit it applied, and canceling an order hands back whatever credit was applied
 * to it.
 */
beforeEach(function (): void {
    config()->set('shops.payment.allow_store_credit', true);
});

/**
 * @return array{0: Order, 1: Customer}
 */
function creditReturnOrder(int $credit = 500): array
{
    $customer = Customer::create(['name' => 'Ada']);
    $customer->modifyCredits($credit, bucket: 'store_credit');

    $order = Order::factory()->create();
    $order->customer()->associate($customer)->save();
    Item::factory()->for($order)->withEurPrice('1000')->state(['quantity' => 1, 'tax_class' => 'zero'])->create();

    return [$order->refresh(), $customer];
}

function decliningGateway(): void
{
    app()->instance(PaymentGateway::class, new class implements PaymentGateway
    {
        public function charge(Order $order): PaymentResult
        {
            return PaymentResult::failure($order->gatewayAmount(), 'declined');
        }

        public function refund(Order $order, Money $amount): PaymentResult
        {
            return PaymentResult::failure($amount);
        }
    });
}

it('keeps no store credit when the gateway declines the charge', function (): void {
    decliningGateway();
    [$order, $customer] = creditReturnOrder();

    $result = Shops::order($order)->charge();

    expect($result->successful)->toBeFalse()
        ->and($customer->creditsBalance(bucket: 'store_credit'))->toBe(500)
        ->and($order->store_credit_applied)->toBeNull()
        ->and($order->refresh()->store_credit_applied)->toBeNull()
        ->and($order->status)->toBe(Status::New);
});

it('loses no store credit when a declined order is then canceled', function (): void {
    decliningGateway();
    [$order, $customer] = creditReturnOrder();

    Shops::order($order)->charge();
    Shops::order($order)->cancel();

    expect($customer->creditsBalance(bucket: 'store_credit'))->toBe(500);
});

it('returns store credit applied to an order when it is canceled', function (): void {
    [$order, $customer] = creditReturnOrder();

    app(StoreCreditTender::class)->apply($order, $customer, Money::ofMinor(300, 'EUR'));

    expect($customer->creditsBalance(bucket: 'store_credit'))->toBe(200);

    $order->refresh()->cancel();

    expect($customer->creditsBalance(bucket: 'store_credit'))->toBe(500)
        ->and($order->store_credit_applied)->toBeNull()
        ->and($order->refresh()->store_credit_applied)->toBeNull();
});

it('returns the credit from a stale copy of the order exactly once', function (): void {
    [$order, $customer] = creditReturnOrder();

    $stale = Order::query()->findOrFail($order->getKey());

    app(StoreCreditTender::class)->apply($order, $customer, Money::ofMinor(300, 'EUR'));

    // The stale copy never saw the credit, but the cancel reads it under the order's lock.
    $stale->cancel();

    expect($customer->creditsBalance(bucket: 'store_credit'))->toBe(500)
        ->and($order->refresh()->store_credit_applied)->toBeNull();
});

it('cancels an order without store credit without touching the buyer balance', function (): void {
    [$order, $customer] = creditReturnOrder();

    $order->cancel();

    expect($customer->creditsBalance(bucket: 'store_credit'))->toBe(500)
        ->and($order->refresh()->status)->toBe(Status::Canceled);
});

it('keeps the credit on the order and logs it when the buyer is not creditable', function (): void {
    Log::spy();

    $order = Order::factory()->create();
    $order->forceFill(['store_credit_applied' => Money::ofMinor(300, 'EUR')])->save();

    expect(app(StoreCreditTender::class)->restore($order->refresh()))->toBeNull();

    Log::shouldHaveReceived('warning')->once();

    expect($order->refresh()->store_credit_applied?->minor())->toBe('300');
});

it('returns nothing for an order without store credit', function (): void {
    [$order] = creditReturnOrder();

    expect(app(StoreCreditTender::class)->restore($order))->toBeNull();
});
