<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use RoundlyConsulting\Money\Exceptions\CurrencyMismatch;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Orders\Actions\ChargeOrderAction;
use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Orders\Item;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Payments\Exceptions\StoreCreditAlreadyAppliedException;
use RoundlyConsulting\Shops\Payments\Exceptions\StoreCreditBucketNotDenominated;
use RoundlyConsulting\Shops\Payments\Exceptions\StoreCreditCurrencyMismatch;
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

    expect($remainder->minor())->toBe('0')
        ->and((string) $order->refresh()->store_credit_applied)->toBe('10.00 EUR')
        ->and($customer->creditsBalance(bucket: 'store_credit'))->toBe(1000);
});

it('applies partial credit and leaves a remainder', function (): void {
    $customer = Customer::create(['name' => 'Ada']);
    $customer->modifyCredits(600, bucket: 'store_credit');
    $order = orderForCustomer($customer);

    $remainder = app(StoreCreditTender::class)->apply($order, $customer);

    expect($remainder->minor())->toBe('400')
        ->and($order->refresh()->store_credit_applied?->equals(Money::ofMinor(600, 'EUR')))->toBeTrue();
});

it('charges nothing to credit when the balance is zero', function (): void {
    $customer = Customer::create(['name' => 'Ada']);
    $order = orderForCustomer($customer);

    $remainder = app(StoreCreditTender::class)->apply($order, $customer);

    expect($remainder->minor())->toBe('1000')
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
    config()->set('shops.payment.allow_store_credit', true);

    $customer = Customer::create(['name' => 'Ada']);
    $customer->modifyCredits(2000, bucket: 'store_credit');
    $order = orderForCustomer($customer);

    app(ChargeOrderAction::class)->execute($order);

    expect($order->refresh()->status)->toBe(Status::Paid)
        ->and($order->store_credit_applied?->minor())->toBe('1000');
});

it('grants store credit back on refund when enabled', function (): void {
    config()->set('shops.payment.refund_to_store_credit', true);

    $customer = Customer::create(['name' => 'Ada']);
    $order = Order::factory()->paid()->create();
    $order->customer()->associate($customer)->save();
    Item::factory()->for($order)->withEurPrice('1000')->state(['quantity' => 1, 'tax_class' => 'zero'])->create();

    $order->refresh()->refund();

    expect($customer->creditsBalance(bucket: 'store_credit'))->toBe(1000);
});

it('debits the store credit bucket the host configured', function (): void {
    config()->set('shops.payment.store_credit_bucket', 'wallet');
    config()->set('credits.currencies.wallet', 'EUR');

    $customer = Customer::create(['name' => 'Ada']);
    $customer->modifyCredits(2000, bucket: 'wallet');
    $order = orderForCustomer($customer);

    $remainder = app(StoreCreditTender::class)->apply($order, $customer);

    // The debit lands on the configured bucket, and the default one is left alone.
    expect($remainder->minor())->toBe('0')
        ->and($customer->creditsBalance(bucket: 'wallet'))->toBe(1000)
        ->and($customer->creditsBalance(bucket: 'store_credit'))->toBe(0);
});

it('grants a refund to the store credit bucket the host configured', function (): void {
    config()->set('shops.payment.refund_to_store_credit', true);
    config()->set('shops.payment.store_credit_bucket', 'wallet');
    config()->set('credits.currencies.wallet', 'EUR');

    $customer = Customer::create(['name' => 'Ada']);
    $order = Order::factory()->paid()->create();
    $order->customer()->associate($customer)->save();
    Item::factory()->for($order)->withEurPrice('1000')->state(['quantity' => 1, 'tax_class' => 'zero'])->create();

    $order->refresh()->refund();

    expect($customer->creditsBalance(bucket: 'wallet'))->toBe(1000)
        ->and($customer->creditsBalance(bucket: 'store_credit'))->toBe(0);
});

it('caps the debit at a requested amount', function (): void {
    $customer = Customer::create(['name' => 'Ada']);
    $customer->modifyCredits(2000, bucket: 'store_credit');
    $order = orderForCustomer($customer);

    $remainder = app(StoreCreditTender::class)->apply($order, $customer, Money::ofMinor(250, 'EUR'));

    expect($remainder->minor())->toBe('750')
        ->and($order->refresh()->store_credit_applied?->minor())->toBe('250')
        ->and($customer->creditsBalance(bucket: 'store_credit'))->toBe(1750);
});

it('refuses a requested amount in another currency', function (): void {
    $customer = Customer::create(['name' => 'Ada']);
    $customer->modifyCredits(2000, bucket: 'store_credit');
    $order = orderForCustomer($customer);

    expect(fn () => app(StoreCreditTender::class)->apply($order, $customer, Money::ofMinor(250, 'USD')))
        ->toThrow(CurrencyMismatch::class)
        ->and($customer->creditsBalance(bucket: 'store_credit'))->toBe(2000);
});

it('refuses an undenominated store-credit bucket', function (): void {
    config()->set('credits.currencies', []);

    $customer = Customer::create(['name' => 'Ada']);
    $customer->modifyCredits(2000, bucket: 'store_credit');
    $order = orderForCustomer($customer);

    expect(fn () => app(StoreCreditTender::class)->apply($order, $customer))
        ->toThrow(StoreCreditBucketNotDenominated::class, 'store_credit')
        ->and($order->refresh()->store_credit_applied)->toBeNull();
});

it('refuses a bucket denominated in another currency than the order', function (): void {
    config()->set('credits.currencies.store_credit', 'USD');

    $customer = Customer::create(['name' => 'Ada']);
    $customer->modifyCredits(2000, bucket: 'store_credit');
    $order = orderForCustomer($customer);

    expect(fn () => app(StoreCreditTender::class)->apply($order, $customer))
        ->toThrow(StoreCreditCurrencyMismatch::class, 'holds USD and cannot pay an order in EUR')
        ->and($customer->creditsBalance(bucket: 'store_credit'))->toBe(2000);
});

it('skips a refund to an undenominated bucket with a warning instead of throwing', function (): void {
    config()->set('shops.payment.refund_to_store_credit', true);
    config()->set('credits.currencies', []);
    Log::spy();

    $customer = Customer::create(['name' => 'Ada']);
    $order = Order::factory()->paid()->create();
    $order->customer()->associate($customer)->save();
    Item::factory()->for($order)->withEurPrice('1000')->state(['quantity' => 1, 'tax_class' => 'zero'])->create();

    $order->refresh()->refund();

    expect($order->refresh()->status)->toBe(Status::Refunded)
        ->and($customer->creditsBalance(bucket: 'store_credit'))->toBe(0);

    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $message, array $context): bool => $context['bucket'] === 'store_credit'
            && $context['bucket_currency'] === null
            && $context['order_currency'] === 'EUR',
    );
});

it('skips a refund when the order total is zero', function (): void {
    config()->set('shops.payment.refund_to_store_credit', true);

    $customer = Customer::create(['name' => 'Ada']);
    $order = Order::factory()->paid()->create();
    $order->customer()->associate($customer)->save();

    $order->refresh()->refund();

    expect($customer->creditsBalance(bucket: 'store_credit'))->toBe(0);
});

/**
 * The regression pin for the whole class of bug: the store-credit feature used to read
 * `shops.payments.*` (plural) while the published config file only ever defines
 * `payment` (singular). Every key resolved to null, so the feature was unreachable —
 * `SHOPS_ALLOW_STORE_CREDIT=true` did nothing at all — and the suite never noticed
 * because it set the same plural key the code read.
 *
 * This asserts the keys the code reads are the keys a host actually gets.
 */
it('reads store credit settings from keys the published config file defines', function (): void {
    /** @var array<string, mixed> $shipped */
    $shipped = require __DIR__.'/../../../../config/shops.php';

    expect($shipped)->toHaveKey('payment');

    /** @var array<string, mixed> $payment */
    $payment = $shipped['payment'];

    expect($payment)
        ->toHaveKey('allow_store_credit')
        ->toHaveKey('store_credit_bucket')
        ->toHaveKey('refund_to_store_credit');

    // Nothing in the package may read a `shops.payments.*` (plural) key: the shipped
    // config file has no such section, so any such read is silently dead.
    $sources = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(__DIR__.'/../../../../src')
    );

    foreach ($sources as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        expect((string) file_get_contents($file->getPathname()))
            ->not->toContain('shops.payments.');
    }
});
