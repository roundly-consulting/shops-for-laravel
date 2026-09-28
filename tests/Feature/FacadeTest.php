<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Facades\Coupons;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Cart\CartItem;
use RoundlyConsulting\Shops\Contracts\ShippingMethod;
use RoundlyConsulting\Shops\Exceptions\ForeignItemException;
use RoundlyConsulting\Shops\Facades\Shops;
use RoundlyConsulting\Shops\Handles\CartHandle;
use RoundlyConsulting\Shops\Inventory\Enums\StockReason;
use RoundlyConsulting\Shops\Inventory\Events\StockAdjusted;
use RoundlyConsulting\Shops\Inventory\Exceptions\InsufficientStockException;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Address;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\OrderAddresses;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\PlaceOrderData;
use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Orders\Exceptions\IllegalStatusTransitionException;
use RoundlyConsulting\Shops\Orders\Item;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Payments\PaymentResult;
use RoundlyConsulting\Shops\Products\ProductVariant;
use RoundlyConsulting\Shops\Shops\Shop;
use RoundlyConsulting\Shops\ShopsManager;
use RoundlyConsulting\Shops\Tests\Fixtures\Customer;

beforeEach(function (): void {
    config()->set('shops.pricing.default_currency', 'EUR');
});

/**
 * @return array{0: Cart, 1: ProductVariant}
 */
function facadeCart(int $stock = 10, string $price = '1000'): array
{
    return [
        Cart::factory()->create(),
        ProductVariant::factory()->withEurPrice($price)->create(['stock' => $stock, 'track_stock' => true]),
    ];
}

it('pins the facade contract', function (): void {
    expect(Shops::class)
        ->toDocumentItsRoot()
        ->toBeFakeable()
        ->toReachEveryAction(__DIR__.'/../../src/Actions');
});

it('resolves the manager once and serves the same API through dependency injection', function (): void {
    [$cart, $variant] = facadeCart();

    $manager = app(ShopsManager::class);

    expect($manager)->toBe(app(ShopsManager::class))
        ->and(Shops::getFacadeRoot())->toBe($manager)
        ->and($manager->cart($cart))->toBeInstanceOf(CartHandle::class);

    $item = $manager->cart($cart)->add($variant, 2);

    expect($item->quantity)->toBe(2)
        ->and($cart->items()->count())->toBe(1);
});

// ── cart ─────────────────────────────────────────────────────────────────────

it('adds, updates, removes and clears cart lines through the facade', function (): void {
    [$cart, $variant] = facadeCart();
    $other = ProductVariant::factory()->withEurPrice('500')->create();

    $line = Shops::cart($cart)->add($variant, 2);
    Shops::cart($cart)->add($variant);
    Shops::cart($cart)->add($other);

    expect($line->refresh()->quantity)->toBe(3)
        ->and($cart->items()->count())->toBe(2);

    expect(Shops::cart($cart)->update($line, 5)?->quantity)->toBe(5)
        ->and(Shops::cart($cart)->update($line, 0))->toBeNull()
        ->and($cart->items()->count())->toBe(1);

    Shops::cart($cart)->remove($cart->items()->sole());

    expect($cart->items()->count())->toBe(0);

    Shops::cart($cart)->add($variant);
    Shops::cart($cart)->add($other);

    expect(Shops::cart($cart)->clear()->items)->toHaveCount(0)
        ->and(CartItem::query()->count())->toBe(0);
});

it('prices a cart and previews a coupon through the facade', function (): void {
    [$cart, $variant] = facadeCart();
    Coupon::factory()->percentage(1000)->active()->create(['code' => 'SAVE10']);

    Shops::cart($cart)->add($variant, 2);

    expect(Shops::cart($cart)->subtotal()->minor())->toBe('2000')
        ->and(Shops::cart($cart)->price()->getFinalPrice()->minor())->toBe('2000')
        ->and(Shops::cart($cart)->price('SAVE10')->getPriceAfterDiscount()->minor())->toBe('1800');
});

it('checks a cart out through the facade', function (): void {
    [$cart, $variant] = facadeCart(stock: 5);
    Shops::cart($cart)->add($variant, 2);

    $order = Shops::cart($cart)->checkout(new PlaceOrderData(note: 'Leave at the door'));

    expect($order)->toBeInstanceOf(Order::class)
        ->and($order->note)->toBe('Leave at the door')
        ->and($order->items()->sole()->quantity)->toBe(2)
        ->and($variant->refresh()->reserved)->toBe(2)
        ->and($cart->items()->count())->toBe(0);
});

it('checks a cart out with no order data', function (): void {
    [$cart, $variant] = facadeCart();
    Shops::cart($cart)->add($variant);

    expect(Shops::cart($cart)->checkout()->status)->toBe(Status::New);
});

it('refuses to touch a line of another cart', function (): void {
    [$mine] = facadeCart();
    [$theirs, $variant] = facadeCart();
    $foreign = Shops::cart($theirs)->add($variant, 2);

    expect(fn () => Shops::cart($mine)->update($foreign, 9))->toThrow(ForeignItemException::class)
        ->and(fn () => Shops::cart($mine)->update($foreign, 0))->toThrow(ForeignItemException::class)
        ->and(fn () => Shops::cart($mine)->remove($foreign))->toThrow(ForeignItemException::class)
        ->and($foreign->refresh()->quantity)->toBe(2)
        ->and($foreign->trashed())->toBeFalse();
});

/**
 * Found by the facade re-inventory: the old ShopManager singleton constructor-injected its
 * actions, and they their collaborators, at first resolution — so a provider fake (or a host
 * rebinding) installed after that was never seen by checkout. The manager now resolves each
 * action from the container per call.
 */
it('sees a coupons fake installed after the manager was resolved', function (): void {
    app(ShopsManager::class);

    $coupons = Coupons::fake();
    $coupons->generate(DiscountType::Percentage, 1000, 'LATE10');

    [$cart, $variant] = facadeCart();
    Shops::cart($cart)->add($variant);

    $order = Shops::cart($cart)->checkout(new PlaceOrderData(couponCode: 'LATE10'));

    $coupons->assertRedeemed('LATE10');

    expect($order->coupon_code)->toBe('LATE10')
        ->and($order->discount?->minor())->toBe('100');
});

// ── orders ───────────────────────────────────────────────────────────────────

it('moves an order through its statuses through the facade', function (): void {
    $order = Order::factory()->create();

    expect(Shops::order($order)->transition(Status::InProgress)->status)->toBe(Status::InProgress)
        ->and(Shops::order($order)->transition(Status::Paid)->status)->toBe(Status::Paid)
        ->and(Shops::order($order)->fulfil()->status)->toBe(Status::Fulfilled)
        ->and(Shops::order($order)->refund()->status)->toBe(Status::Refunded)
        ->and($order->refresh()->refunded_at)->not->toBeNull();
});

it('cancels an order through the facade and releases its stock', function (): void {
    [$cart, $variant] = facadeCart(stock: 5);
    Shops::cart($cart)->add($variant, 2);
    $order = Shops::cart($cart)->checkout();

    Shops::order($order)->cancel();

    expect($order->refresh()->status)->toBe(Status::Canceled)
        ->and($variant->refresh()->reserved)->toBe(0)
        ->and($variant->stock)->toBe(5);
});

it('refuses an illegal transition through the facade', function (): void {
    $order = Order::factory()->create();

    expect(fn () => Shops::order($order)->refund())->toThrow(IllegalStatusTransitionException::class);
});

it('charges an order through the facade', function (): void {
    $order = Order::factory()->create();
    Item::factory()->for($order)->withEurPrice('1000')->create();

    $result = Shops::order($order)->charge();

    expect($result)->toBeInstanceOf(PaymentResult::class)
        ->and($result->successful)->toBeTrue()
        ->and($order->refresh()->status)->toBe(Status::Paid);
});

it('quotes shipping through the facade', function (): void {
    app()->bind(ShippingMethod::class, fn (): ShippingMethod => new class implements ShippingMethod
    {
        public function quote(Order $order, Address $destination): Money
        {
            return Money::ofMinor($destination->countryIso === 'GB' ? 900 : 500, 'EUR');
        }

        public function label(): string
        {
            return 'Courier';
        }
    });

    $order = Order::factory()->create();

    expect(Shops::order($order)->quoteShipping(new Address('Ada', '1 Way', 'London', 'EC1', 'GB'))->minor())->toBe('900')
        ->and(Shops::order($order)->quoteShipping(new Address('Ada', '1 Rue', 'Paris', '75001', 'FR'))->minor())->toBe('500');
});

it('routes the order convenience methods through the manager', function (): void {
    $order = Order::factory()->create();

    expect($order->markInProgress()->status)->toBe(Status::InProgress)
        ->and($order->markPaid()->status)->toBe(Status::Paid)
        ->and($order->markFulfilled()->status)->toBe(Status::Fulfilled)
        ->and($order->refund()->status)->toBe(Status::Refunded);

    $other = Order::factory()->create();

    expect($other->transitionTo(Status::InProgress)->status)->toBe(Status::InProgress)
        ->and($other->cancel()->status)->toBe(Status::Canceled);
});

// ── inventory ────────────────────────────────────────────────────────────────

it('receives, returns and corrects stock through the facade', function (): void {
    Event::fake([StockAdjusted::class]);
    $variant = ProductVariant::factory()->withEurPrice('1000')->create(['stock' => 2, 'track_stock' => true]);
    $supplier = Shop::factory()->create();

    $received = Shops::inventory($variant)->receive(10, ref: $supplier, note: 'PO-17');

    expect($received->reason)->toBe(StockReason::Received)
        ->and($received->quantity)->toBe(10)
        ->and($received->note)->toBe('PO-17')
        ->and($received->reference?->is($supplier))->toBeTrue()
        ->and(Shops::inventory($variant)->available())->toBe(12);

    $corrected = Shops::inventory($variant)->adjust(-2, note: 'Damaged in storage');

    expect($corrected->reason)->toBe(StockReason::Manual)
        ->and(Shops::inventory($variant)->available())->toBe(10)
        ->and(Shops::inventory($variant)->inStock(10))->toBeTrue()
        ->and(Shops::inventory($variant)->inStock(11))->toBeFalse();

    $sold = Shops::inventory($variant)->adjust(-3, StockReason::Sold, note: 'Market stall');

    expect($sold->reason)->toBe(StockReason::Sold)
        ->and($variant->refresh()->stock)->toBe(7);

    Event::assertDispatchedTimes(StockAdjusted::class, 3);
});

it('books a return against the order it came from', function (): void {
    [$cart, $variant] = facadeCart(stock: 5);
    Shops::cart($cart)->add($variant, 2);
    $order = Shops::cart($cart)->checkout();
    Shops::order($order)->transition(Status::InProgress);
    Shops::order($order)->transition(Status::Paid);
    Shops::order($order)->fulfil();

    expect($variant->refresh()->stock)->toBe(3);

    $return = Shops::inventory($variant)->returned(1, $order, 'Wrong size');

    expect($return->reason)->toBe(StockReason::Returned)
        ->and($return->reference?->is($order))->toBeTrue()
        ->and($variant->refresh()->stock)->toBe(4);
});

it('refuses a return against an order the variant was never on', function (): void {
    [$cart, $variant] = facadeCart();
    Shops::cart($cart)->add($variant);
    $order = Shops::cart($cart)->checkout();
    $stranger = ProductVariant::factory()->withEurPrice('1000')->create(['stock' => 3]);

    expect(fn () => Shops::inventory($stranger)->returned(1, $order))->toThrow(ForeignItemException::class, 'is not on order')
        ->and($stranger->refresh()->stock)->toBe(3);
});

it('refuses a stock correction that would oversell', function (): void {
    $variant = ProductVariant::factory()->withEurPrice('1000')->create(['stock' => 1, 'track_stock' => true]);

    expect(fn () => Shops::inventory($variant)->adjust(-2))->toThrow(InsufficientStockException::class);
});

// ── coupons, tenancy, addresses ───────────────────────────────────────────────

it('previews a coupon discount through the facade', function (): void {
    Coupon::factory()->percentage(1000)->active()->create(['code' => 'SAVE10']);

    $result = Shops::coupons()->preview('SAVE10', Money::ofMinor(1000, 'EUR'));

    expect($result->discount->minor())->toBe('100')
        ->and($result->found)->toBeTrue()
        ->and(Shops::coupons()->preview('NOPE', Money::ofMinor(1000, 'EUR'))->found)->toBeFalse();
});

it('binds the current shop through the facade', function (): void {
    $shop = Shop::factory()->create();
    $scoped = Shop::factory()->create();

    Shops::current()->set($shop);

    expect(Shops::current()->get()?->is($shop))->toBeTrue()
        ->and(Shop::current()?->is($shop))->toBeTrue()
        ->and(Shops::current()->run($scoped, fn (): ?int => Shops::current()->id()))->toBe($scoped->id)
        ->and(Shops::current()->id())->toBe($shop->id);

    Shops::current()->forget();

    expect(Shops::current()->get())->toBeNull();
});

it('resolves a customer default addresses through the facade', function (): void {
    $customer = Customer::create(['name' => 'Ada']);
    $customer->addAddress(AddressData::make(
        city: 'London', street: '1 Shipping Way', postalCode: 'EC1', countryIso: 'GB',
        name: 'Ada Ship', type: AddressType::Shipping, isPrimary: true,
    ));

    $defaults = Shops::addresses()->defaults($customer);

    expect($defaults)->toBeInstanceOf(OrderAddresses::class)
        ->and($defaults->shipping?->city)->toBe('London')
        ->and($defaults->billing?->city)->toBe('London')
        ->and(Shops::addresses()->defaults($customer, billingSameAsShipping: false)->billing)->toBeNull();
});
