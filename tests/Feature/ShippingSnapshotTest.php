<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Money\Exceptions\CurrencyMismatch;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Contracts\ShippingMethod;
use RoundlyConsulting\Shops\Facades\Shops;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Address;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\PlaceOrderData;
use RoundlyConsulting\Shops\Orders\Exceptions\CheckoutRefusedException;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Products\ProductVariant;

/**
 * Checkout quotes shipping for the shipping address through the bound ShippingMethod and
 * snapshots it onto the order (`shipping_cost`), so it is part of the final price and the
 * charge — and a free-shipping coupon really takes it off.
 */
final class PerItemShipping implements ShippingMethod
{
    /** @var list<int> */
    public array $itemsSeen = [];

    public function __construct(
        private readonly string $currency = 'EUR',
        private readonly int $perItemMinor = 250,
    ) {}

    public function quote(Order $order, Address $destination): Money
    {
        $quantity = (int) $order->items->sum('quantity');
        $this->itemsSeen[] = $quantity;

        return Money::ofMinor($this->perItemMinor * $quantity, $this->currency);
    }

    public function label(): string
    {
        return 'Per item';
    }
}

function shippingMethod(string $currency = 'EUR', int $perItemMinor = 250): PerItemShipping
{
    $method = new PerItemShipping($currency, $perItemMinor);

    app()->instance(ShippingMethod::class, $method);

    return $method;
}

function shippingCart(int $quantity = 2, ?string $coupon = null): Cart
{
    $cart = Cart::create(['currency' => 'EUR', 'coupon_code' => $coupon]);
    $variant = ProductVariant::factory()->withEurPrice('1000')->create(['stock' => 10, 'tax_class' => 'zero']);
    Shops::cart($cart)->add($variant, $quantity);

    return $cart;
}

function destination(): Address
{
    return new Address('Ada', '1 Way', 'London', 'EC1', 'GB');
}

it('quotes shipping for the destination and puts it in the order total', function (): void {
    $method = shippingMethod();

    $order = Shops::cart(shippingCart(2))->checkout(new PlaceOrderData(shipping: destination()));

    // The method saw the order's lines (2 items × 2.50).
    expect($method->itemsSeen)->toBe([2])
        ->and((string) $order->shipping_cost)->toBe('5.00 EUR')
        ->and((string) $order->price->shippingCost())->toBe('5.00 EUR')
        ->and((string) $order->price->getFinalPrice())->toBe('25.00 EUR')
        ->and((string) $order->gatewayAmount())->toBe('25.00 EUR');
});

it('charges the gateway the goods plus shipping', function (): void {
    shippingMethod();

    $order = Shops::cart(shippingCart(1))->checkout(new PlaceOrderData(shipping: destination()));

    expect((string) Shops::order($order)->charge()->amount)->toBe('12.50 EUR');
});

it('takes the shipping off with a free-shipping coupon', function (): void {
    shippingMethod();
    Coupon::factory()->freeShipping()->active()->create(['code' => 'SHIP']);

    $order = Shops::cart(shippingCart(2, 'SHIP'))->checkout(new PlaceOrderData(shipping: destination()));

    expect($order->free_shipping)->toBeTrue()
        ->and((string) $order->shipping_cost)->toBe('5.00 EUR')
        ->and((string) $order->price->shippingCost())->toBe('0.00 EUR')
        ->and((string) $order->price->getFinalPrice())->toBe('20.00 EUR');
});

it('uses an explicitly chosen shipping cost instead of quoting', function (): void {
    $method = shippingMethod();

    $order = Shops::cart(shippingCart(1))->checkout(new PlaceOrderData(
        shipping: destination(),
        shippingCost: Money::ofMinor(990, 'EUR'),
    ));

    expect($method->itemsSeen)->toBe([])
        ->and((string) $order->price->getFinalPrice())->toBe('19.90 EUR');
});

it('quotes nothing for an order without a shipping address', function (): void {
    $method = shippingMethod();

    $order = Shops::cart(shippingCart(1))->checkout();

    expect($method->itemsSeen)->toBe([])
        ->and($order->shipping_cost)->toBeNull()
        ->and((string) $order->price->getFinalPrice())->toBe('10.00 EUR');
});

it('refuses shipping quoted in another currency, writing nothing', function (): void {
    shippingMethod('USD');
    $cart = shippingCart(1);

    expect(fn () => Shops::cart($cart)->checkout(new PlaceOrderData(shipping: destination())))
        ->toThrow(CurrencyMismatch::class);

    expect(Order::query()->count())->toBe(0)
        ->and($cart->refresh()->items()->count())->toBe(1);
});

it('refuses a negative shipping cost', function (): void {
    $cart = shippingCart(1);

    expect(fn () => Shops::cart($cart)->checkout(new PlaceOrderData(shippingCost: Money::ofMinor(-100, 'EUR'))))
        ->toThrow(CheckoutRefusedException::class);

    expect(Order::query()->count())->toBe(0);
});

it('stays zero-config with the default free shipping method', function (): void {
    $order = Shops::cart(shippingCart(1))->checkout(new PlaceOrderData(shipping: destination()));

    expect((string) $order->shipping_cost)->toBe('0.00 EUR')
        ->and((string) $order->price->getFinalPrice())->toBe('10.00 EUR');
});
