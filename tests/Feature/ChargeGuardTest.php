<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Actions\Orders\ChargeOrderAction;
use RoundlyConsulting\Shops\Contracts\PaymentGateway;
use RoundlyConsulting\Shops\Facades\Shops;
use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Orders\Events\OrderPaid;
use RoundlyConsulting\Shops\Orders\Exceptions\IllegalStatusTransitionException;
use RoundlyConsulting\Shops\Orders\Item;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Payments\PaymentResult;
use RoundlyConsulting\Shops\Tests\Fixtures\Customer;
use RoundlyConsulting\Testing\Fixtures\LockRecorder;

/**
 * A charge moves money, so the order's status is the gate — checked under the order's row
 * lock BEFORE store credit or the gateway is touched. Only a New or InProgress order can be
 * charged; anything else (a double-clicked "Pay", a canceled order) is refused with nothing
 * charged and no credit debited.
 */
final class ChargeGuardGateway implements PaymentGateway
{
    /** @var list<string> */
    public array $charged = [];

    /** @var list<int> */
    public array $locksSeen = [];

    public bool $decline = false;

    public function charge(Order $order): PaymentResult
    {
        $this->charged[] = $order->number.':'.$order->gatewayAmount();
        $this->locksSeen[] = count(LockRecorder::recorded());

        return $this->decline
            ? PaymentResult::failure($order->gatewayAmount(), 'declined')
            : PaymentResult::success($order->gatewayAmount(), 'ch_1');
    }

    public function refund(Order $order, Money $amount): PaymentResult
    {
        return PaymentResult::success($amount);
    }
}

function chargeGuardGateway(): ChargeGuardGateway
{
    $gateway = new ChargeGuardGateway;

    app()->instance(PaymentGateway::class, $gateway);

    return $gateway;
}

function chargeGuardOrder(Status $status = Status::New): Order
{
    $order = Order::factory()->withStatus($status)->create();
    Item::factory()->for($order)->withEurPrice('1000')->state(['quantity' => 1, 'tax_class' => 'zero'])->create();

    return $order->refresh();
}

it('refuses to charge an order that is not chargeable, before touching the gateway', function (Status $status): void {
    $gateway = chargeGuardGateway();
    $order = chargeGuardOrder($status);

    expect(fn () => app(ChargeOrderAction::class)->execute($order))
        ->toThrow(IllegalStatusTransitionException::class);

    expect($gateway->charged)->toBe([])
        ->and($order->refresh()->status)->toBe($status);
})->with([
    'paid' => Status::Paid,
    'fulfilled' => Status::Fulfilled,
    'canceled' => Status::Canceled,
    'refunded' => Status::Refunded,
]);

it('never charges an order twice', function (): void {
    $gateway = chargeGuardGateway();
    $order = chargeGuardOrder();

    Shops::order($order)->charge();

    expect(fn () => Shops::order($order)->charge())->toThrow(IllegalStatusTransitionException::class);

    expect($gateway->charged)->toHaveCount(1)
        ->and($order->refresh()->status)->toBe(Status::Paid);
});

it('refuses a second charge made from a stale copy of the order', function (): void {
    $gateway = chargeGuardGateway();
    $order = chargeGuardOrder();

    // Two requests loaded the order while it was New (a double-clicked "Pay").
    $first = Order::query()->findOrFail($order->getKey());
    $second = Order::query()->findOrFail($order->getKey());

    app(ChargeOrderAction::class)->execute($first);

    expect($second->status)->toBe(Status::New);

    expect(fn () => app(ChargeOrderAction::class)->execute($second))
        ->toThrow(IllegalStatusTransitionException::class);

    expect($gateway->charged)->toHaveCount(1);
});

it('never debits store credit for a canceled order', function (): void {
    config()->set('shops.payment.allow_store_credit', true);
    $gateway = chargeGuardGateway();

    $customer = Customer::create(['name' => 'Ada']);
    $customer->modifyCredits(500, bucket: 'store_credit');

    $order = chargeGuardOrder(Status::Canceled);
    $order->customer()->associate($customer)->save();

    expect(fn () => app(ChargeOrderAction::class)->execute($order->refresh()))
        ->toThrow(IllegalStatusTransitionException::class);

    expect($customer->creditsBalance(bucket: 'store_credit'))->toBe(500)
        ->and($order->refresh()->store_credit_applied)->toBeNull()
        ->and($gateway->charged)->toBe([]);
});

it('locks the order row before the gateway is called', function (): void {
    $gateway = chargeGuardGateway();
    $order = chargeGuardOrder();
    $baseline = DB::transactionLevel();

    $locks = recordLocks(function () use ($order): void {
        app(ChargeOrderAction::class)->execute($order);
    });

    // The first lock is the order row, taken inside the charge's own transaction — and it
    // was already held when the gateway was asked to charge.
    expect($locks[0]['sql'])->toContain('orders')
        ->and($locks[0]['transactionDepth'])->toBe($baseline + 1)
        ->and($gateway->locksSeen[0])->toBeGreaterThanOrEqual(1);
});

it('keeps a committed charge when an OrderPaid listener throws', function (): void {
    $gateway = chargeGuardGateway();
    $order = chargeGuardOrder();

    Event::listen(OrderPaid::class, function (): void {
        throw new RuntimeException('mailer down');
    });

    // The listener runs after the charge committed: the money was taken, so the order is Paid.
    expect(fn () => Shops::order($order)->charge())->toThrow(RuntimeException::class, 'mailer down');

    expect($gateway->charged)->toHaveCount(1)
        ->and($order->status)->toBe(Status::Paid)
        ->and($order->refresh()->status)->toBe(Status::Paid);
});

it('fires the order events only once the surrounding transaction commits', function (): void {
    chargeGuardGateway();
    $order = chargeGuardOrder();
    $fired = [];

    Event::listen(OrderPaid::class, function () use (&$fired): void {
        $fired[] = DB::transactionLevel();
    });

    DB::transaction(function () use ($order, &$fired): void {
        Shops::order($order)->charge();

        expect($fired)->toBe([]);
    });

    expect($fired)->toBe([0]);
});

it('fires nothing for a charge a host transaction rolls back', function (): void {
    chargeGuardGateway();
    $order = chargeGuardOrder();
    $fired = 0;

    Event::listen(OrderPaid::class, function () use (&$fired): void {
        $fired++;
    });

    try {
        DB::transaction(function () use ($order): void {
            Shops::order($order)->charge();

            throw new RuntimeException('host rolled back');
        });
    } catch (RuntimeException) {
    }

    expect($fired)->toBe(0)
        ->and($order->refresh()->status)->toBe(Status::New);
});
