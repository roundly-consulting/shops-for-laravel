<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Orders\Actions\ChargeOrderAction;
use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Orders\Item;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Payments\Exceptions\StoreCreditAlreadyAppliedException;
use RoundlyConsulting\Shops\Payments\StoreCreditTender;
use RoundlyConsulting\Shops\Tests\Fixtures\Customer;

function orderForCustomer(Customer $customer, string $price = '1000'): Order
{
    $order = Order::factory()->create();
    $order->customer()->associate($customer)->save();
    Item::factory()->for($order)->withEurPrice($price)->state(['quantity' => 1, 'tax_class' => 'zero'])->create();

    return $order->refresh();
}

it('debits the full order total when credit covers it', function (): void {
    $customer = Customer::create(['name' => 'Ada']);
    $customer->modifyCredits(2000, bucket: 'store_credit');
    $order = orderForCustomer($customer);

    $remainder = app(StoreCreditTender::class)->apply($order, $customer);

    expect($remainder->getMinorAmount())->toBe(0)
        ->and($order->refresh()->store_credit_applied)->toBe(1000)
        ->and($customer->creditsBalance(bucket: 'store_credit'))->toBe(1000);
});

it('applies partial credit and leaves a remainder', function (): void {
    $customer = Customer::create(['name' => 'Ada']);
    $customer->modifyCredits(600, bucket: 'store_credit');
    $order = orderForCustomer($customer);

    $remainder = app(StoreCreditTender::class)->apply($order, $customer);

    expect($remainder->getMinorAmount())->toBe(400)
        ->and($order->refresh()->store_credit_applied)->toBe(600);
});

it('charges nothing to credit when the balance is zero', function (): void {
    $customer = Customer::create(['name' => 'Ada']);
    $order = orderForCustomer($customer);

    $remainder = app(StoreCreditTender::class)->apply($order, $customer);

    expect($remainder->getMinorAmount())->toBe(1000)
        ->and($order->refresh()->store_credit_applied)->toBeNull();
});

it('guards against double application', function (): void {
    $customer = Customer::create(['name' => 'Ada']);
    $customer->modifyCredits(2000, bucket: 'store_credit');
    $order = orderForCustomer($customer);

    app(StoreCreditTender::class)->apply($order, $customer);

    expect(fn () => app(StoreCreditTender::class)->apply($order->refresh(), $customer))
        ->toThrow(StoreCreditAlreadyAppliedException::class);
});

it('applies store credit as a charge pre-step when enabled', function (): void {
    config()->set('shops.payments.allow_store_credit', true);

    $customer = Customer::create(['name' => 'Ada']);
    $customer->modifyCredits(2000, bucket: 'store_credit');
    $order = orderForCustomer($customer);

    app(ChargeOrderAction::class)->execute($order);

    expect($order->refresh()->status)->toBe(Status::Paid)
        ->and($order->store_credit_applied)->toBe(1000);
});

it('grants store credit back on refund when enabled', function (): void {
    config()->set('shops.payments.refund_to_store_credit', true);

    $customer = Customer::create(['name' => 'Ada']);
    $order = Order::factory()->paid()->create();
    $order->customer()->associate($customer)->save();
    Item::factory()->for($order)->withEurPrice('1000')->state(['quantity' => 1, 'tax_class' => 'zero'])->create();

    $order->refresh()->refund();

    expect($customer->creditsBalance(bucket: 'store_credit'))->toBe(1000);
});
