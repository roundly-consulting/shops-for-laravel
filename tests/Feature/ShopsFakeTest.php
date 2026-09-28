<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Facades\Shops;
use RoundlyConsulting\Shops\Inventory\Enums\StockReason;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\PlaceOrderData;
use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Orders\Exceptions\IllegalStatusTransitionException;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Products\ProductVariant;
use RoundlyConsulting\Shops\ShopsManager;
use RoundlyConsulting\Shops\Testing\CartChange;
use RoundlyConsulting\Shops\Testing\ShopsFake;

beforeEach(function (): void {
    config()->set('shops.pricing.default_currency', 'EUR');
});

function fakeVariant(int $stock = 10): ProductVariant
{
    return ProductVariant::factory()->withEurPrice('1000')->create(['stock' => $stock, 'track_stock' => true]);
}

it('swaps a recording subtype of the manager behind the facade and the container', function (): void {
    $fake = Shops::fake();

    expect($fake)->toBeInstanceOf(ShopsFake::class)
        ->toBeInstanceOf(ShopsManager::class)
        ->and(app(ShopsManager::class))->toBe($fake)
        ->and(Shops::getFacadeRoot())->toBe($fake);
});

it('starts with nothing recorded', function (): void {
    $fake = Shops::fake();

    $fake->assertNothingPlaced();
    $fake->assertNothingCharged();
    $fake->assertNothingTransitioned();
    $fake->assertNothingStockAdjusted();
    $fake->assertNothingCartChanged();

    expect(fn () => $fake->assertOrderPlaced())->toThrow(AssertionFailedError::class, 'Expected an order to be placed')
        ->and(fn () => $fake->assertCharged())->toThrow(AssertionFailedError::class, 'Expected an order to be charged')
        ->and(fn () => $fake->assertTransitioned())->toThrow(AssertionFailedError::class, 'Expected an order to be transitioned')
        ->and(fn () => $fake->assertStockAdjusted())->toThrow(AssertionFailedError::class, 'Expected the stock of a variant')
        ->and(fn () => $fake->assertCartChanged())->toThrow(AssertionFailedError::class, 'Expected a cart to be changed');
});

it('records cart changes made through the facade and the Cart model', function (): void {
    $fake = Shops::fake();
    $cart = Cart::factory()->create();
    $other = Cart::factory()->create();
    $variant = fakeVariant();

    // Through the model convenience method — it must reach the fake too.
    $line = $cart->add($variant, 2);

    Shops::assertCartChanged($cart, CartChange::Added);
    Shops::assertCartChanged();

    expect(fn () => Shops::assertCartChanged($other))->toThrow(AssertionFailedError::class, "cart [{$other->id}]")
        ->and(fn () => Shops::assertCartChanged($cart, CartChange::Cleared))->toThrow(AssertionFailedError::class, '(cleared)')
        ->and(fn () => Shops::assertNothingCartChanged())->toThrow(AssertionFailedError::class, 'Expected no cart change, but 1 were made.');

    Shops::cart($cart)->update($line, 3);
    Shops::cart($cart)->update($line, 0);
    $again = Shops::cart($cart)->add($variant);
    Shops::cart($cart)->remove($again);
    Shops::cart($cart)->clear();

    $fake->assertCartChanged($cart, CartChange::Updated);
    $fake->assertCartChanged($cart, CartChange::Removed);
    $fake->assertCartChanged($cart, CartChange::Cleared);

    // The operations still ran.
    expect($cart->items()->count())->toBe(0);
});

it('records a checkout and not its internal reservations', function (): void {
    $fake = Shops::fake();
    $cart = Cart::factory()->create();
    $untouched = Cart::factory()->create();
    Shops::cart($cart)->add(fakeVariant(), 2);

    $order = Shops::cart($cart)->checkout(new PlaceOrderData(note: 'gift'));

    $fake->assertOrderPlaced();
    $fake->assertOrderPlaced($cart);
    $fake->assertNothingStockAdjusted();

    expect($order->exists)->toBeTrue()
        ->and(fn () => $fake->assertOrderPlaced($untouched))->toThrow(AssertionFailedError::class, "from cart [{$untouched->id}]")
        ->and(fn () => $fake->assertNothingPlaced())->toThrow(AssertionFailedError::class, 'Expected no order to be placed, but 1 were.');
});

it('records transitions made through the facade and the Order model', function (): void {
    $fake = Shops::fake();
    $order = Order::factory()->create();
    $other = Order::factory()->create();

    $order->markInProgress();
    $order->markPaid();
    Shops::order($order)->fulfil();

    $fake->assertTransitioned();
    $fake->assertTransitioned($order);
    $fake->assertTransitioned($order, Status::Paid);
    $fake->assertTransitioned($order, Status::Fulfilled);

    expect(fn () => $fake->assertTransitioned($other))->toThrow(AssertionFailedError::class, "order [{$other->number}]")
        ->and(fn () => $fake->assertTransitioned($order, Status::Canceled))->toThrow(AssertionFailedError::class, 'to Canceled')
        ->and(fn () => $fake->assertNothingTransitioned())->toThrow(AssertionFailedError::class, '3 transition(s)');

    $other->cancel();

    $fake->assertTransitioned($other, Status::Canceled);
});

it('does not record a refused transition', function (): void {
    $fake = Shops::fake();
    $order = Order::factory()->create();

    expect(fn () => $order->refund())->toThrow(IllegalStatusTransitionException::class);

    $fake->assertNothingTransitioned();
});

it('records charges', function (): void {
    $fake = Shops::fake();
    $order = Order::factory()->create();
    $other = Order::factory()->create();

    $result = Shops::order($order)->charge();

    $fake->assertCharged();
    $fake->assertCharged($order);

    expect($result->successful)->toBeTrue()
        ->and($order->refresh()->status)->toBe(Status::Paid)
        ->and(fn () => $fake->assertCharged($other))->toThrow(AssertionFailedError::class, "order [{$other->number}] to be charged")
        ->and(fn () => $fake->assertNothingCharged())->toThrow(AssertionFailedError::class, '1 charge(s)');
});

it('records stock adjustments', function (): void {
    $fake = Shops::fake();
    $variant = fakeVariant(stock: 5);
    $other = fakeVariant();

    Shops::inventory($variant)->receive(10);
    Shops::inventory($variant)->adjust(-1);

    $fake->assertStockAdjusted();
    $fake->assertStockAdjusted($variant);
    $fake->assertStockAdjusted($variant, 10, StockReason::Received);
    $fake->assertStockAdjusted($variant, -1, StockReason::Manual);

    expect($variant->refresh()->stock)->toBe(14)
        ->and(fn () => $fake->assertStockAdjusted($other))->toThrow(AssertionFailedError::class, "variant [{$other->sku}]")
        ->and(fn () => $fake->assertStockAdjusted($variant, 3))->toThrow(AssertionFailedError::class, 'by 3')
        ->and(fn () => $fake->assertStockAdjusted($variant, reason: StockReason::Returned))->toThrow(AssertionFailedError::class, '(Returned)')
        ->and(fn () => $fake->assertNothingStockAdjusted())->toThrow(AssertionFailedError::class, 'but 2 were made');
});

it('serves a constructor-injected manager the fake', function (): void {
    $fake = Shops::fake();
    $cart = Cart::factory()->create();

    $service = new class(app(ShopsManager::class))
    {
        public function __construct(public ShopsManager $shops) {}
    };

    $service->shops->cart($cart)->add(fakeVariant());

    expect($service->shops)->toBe($fake);

    $fake->assertCartChanged($cart, CartChange::Added);
});
