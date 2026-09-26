<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Contracts\DiscountResolver;
use RoundlyConsulting\Shops\Discounts\CouponPackageDiscountResolver;
use RoundlyConsulting\Shops\Discounts\DiscountResult;
use RoundlyConsulting\Shops\Orders\Actions\ChargeOrderAction;
use RoundlyConsulting\Shops\Orders\Actions\PlaceOrderAction;
use RoundlyConsulting\Shops\Orders\Events\OrderPlaced;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Payments\StoreCreditTender;
use RoundlyConsulting\Shops\Products\ProductVariant;
use RoundlyConsulting\Shops\Tests\Fixtures\Customer;

/**
 * An order's discount is what the buyer was granted when the order was placed. It must
 * not follow the coupon afterwards: expiring, revoking or using up the coupon — including
 * the single use this very order consumed — never changes what the order costs, so the
 * gateway charge, the store-credit debit and the refund credit-back all see the same total.
 */
beforeEach(function (): void {
    config()->set('shops.pricing.default_currency', 'EUR');
    config()->set('shops.pricing.price_type', 'gross');
});

/**
 * @param  array<string, string>  $lines  price (minor) => tax class
 */
function placeDiscountedOrder(string $code, array $lines = ['1000' => 'zero'], ?Customer $customer = null): Order
{
    $cart = Cart::factory()->create(['currency' => 'EUR', 'coupon_code' => $code]);

    if ($customer !== null) {
        $cart->owner()->associate($customer)->save();
    }

    foreach ($lines as $price => $taxClass) {
        $cart->add(ProductVariant::factory()->withEurPrice((string) $price)->create(['stock' => 10, 'tax_class' => $taxClass]));
    }

    return app(PlaceOrderAction::class)->execute($cart);
}

function freshOrder(Order $order): Order
{
    return Order::query()->findOrFail($order->id);
}

it('keeps the placed discount after the coupon expires', function (): void {
    $coupon = Coupon::factory()->percentage(1000)->active()->create(['code' => 'SAVE10']);
    $order = placeDiscountedOrder('SAVE10');

    $coupon->expire(now()->subMinute())->save();

    expect(freshOrder($order)->price->getFinalPrice()->minor())->toBe('900');
});

it('keeps the placed discount after the coupon is deactivated or deleted', function (): void {
    $coupon = Coupon::factory()->percentage(1000)->active()->create(['code' => 'SAVE10']);
    $order = placeDiscountedOrder('SAVE10');

    $coupon->forceFill(['activated_at' => null])->save();
    expect(freshOrder($order)->price->getFinalPrice()->minor())->toBe('900');

    $coupon->delete();
    expect(freshOrder($order)->price->getFinalPrice()->minor())->toBe('900');
});

it('keeps the placed discount after other buyers exhaust the coupon', function (): void {
    $coupon = Coupon::factory()->percentage(1000)->active()->create(['code' => 'SAVE10', 'max_usage' => 2]);
    $order = placeDiscountedOrder('SAVE10');

    $coupon->refresh()->redeemBy(null, Money::ofMinor(5000, 'EUR'));

    expect($coupon->refresh()->isAtMaximumUsage())->toBeTrue()
        ->and(freshOrder($order)->price->getFinalPrice()->minor())->toBe('900');
});

it('keeps the discount of a single-use coupon the order itself consumed', function (): void {
    $coupon = Coupon::factory()->fixed(250, 'EUR')->active()->create(['code' => 'ONCE', 'max_usage' => 1]);

    $order = placeDiscountedOrder('ONCE');

    expect($coupon->refresh()->usage)->toBe(1)
        ->and($order->price->getDiscountValue()->minor())->toBe('250')
        ->and(freshOrder($order)->price->getFinalPrice()->minor())->toBe('750');
});

it('charges the snapshotted total after the coupon expires', function (): void {
    $coupon = Coupon::factory()->percentage(1000)->active()->create(['code' => 'SAVE10']);
    $order = placeDiscountedOrder('SAVE10');
    $coupon->expire(now()->subMinute())->save();

    $result = app(ChargeOrderAction::class)->execute(freshOrder($order));

    expect($result->successful)->toBeTrue()
        ->and($result->amount->minor())->toBe('900');
});

it('debits store credit for the snapshotted total after the coupon expires', function (): void {
    $customer = Customer::create(['name' => 'Ada']);
    $customer->modifyCredits(2000, bucket: 'store_credit');
    $coupon = Coupon::factory()->percentage(1000)->active()->create(['code' => 'SAVE10']);
    $order = placeDiscountedOrder('SAVE10', customer: $customer);
    $coupon->expire(now()->subMinute())->save();

    $remainder = app(StoreCreditTender::class)->apply(freshOrder($order), $customer);

    expect($remainder->minor())->toBe('0')
        ->and(freshOrder($order)->store_credit_applied?->minor())->toBe('900')
        ->and($customer->creditsBalance(bucket: 'store_credit'))->toBe(1100);
});

it('credits back the snapshotted total on refund after the coupon expires', function (): void {
    config()->set('shops.payment.refund_to_store_credit', true);

    $customer = Customer::create(['name' => 'Ada']);
    $coupon = Coupon::factory()->percentage(1000)->active()->create(['code' => 'SAVE10', 'max_usage' => 1]);
    $order = placeDiscountedOrder('SAVE10', customer: $customer);
    $coupon->expire(now()->subMinute())->save();

    $order = freshOrder($order)->markInProgress()->markPaid();
    freshOrder($order)->refund();

    expect($customer->creditsBalance(bucket: 'store_credit'))->toBe(900);
});

it('still allocates the snapshotted discount across lines for per-line tax', function (): void {
    // 10 % off 23.00 = 2.30, split 1.20 / 1.10 by line total; each line taxed on its share.
    $coupon = Coupon::factory()->percentage(1000)->active()->create(['code' => 'SAVE10']);
    $order = placeDiscountedOrder('SAVE10', ['1200' => 'standard', '1100' => 'reduced']);
    $coupon->expire(now()->subMinute())->save();

    $price = freshOrder($order)->price;

    expect($price->getDiscountValue()->minor())->toBe('230')
        ->and($price->getTaxPrice()->minor())->toBe('270')
        ->and($price->getNetPrice()->minor())->toBe('1800')
        ->and($price->getFinalPrice()->minor())->toBe('2070');
});

it('records the discount snapshot on the order row', function (): void {
    Coupon::factory()->percentage(1000)->active()->create(['code' => 'SAVE10']);

    $order = freshOrder(placeDiscountedOrder('SAVE10'));

    expect((string) $order->discount)->toBe('1.00 EUR')
        ->and($order->free_shipping)->toBeFalse()
        ->and($order->coupon_code)->toBe('SAVE10');
});

it('keeps a free-shipping grant after the coupon expires', function (): void {
    $coupon = Coupon::factory()->freeShipping()->active()->create(['code' => 'SHIP']);
    $order = placeDiscountedOrder('SHIP');

    $coupon->expire(now()->subMinute())->save();
    $price = freshOrder($order)->price;

    expect(freshOrder($order)->free_shipping)->toBeTrue()
        ->and($price->freeShipping)->toBeTrue()
        ->and($price->getDiscountValue()->minor())->toBe('0');
});

it('snapshots nothing when the coupon is not redeemable at placement', function (): void {
    Coupon::factory()->percentage(1000)->expired()->create(['code' => 'GONE']);

    $order = freshOrder(placeDiscountedOrder('GONE'));

    expect($order->discount)->toBeNull()
        ->and($order->coupon_code)->toBeNull()
        ->and($order->price->getFinalPrice()->minor())->toBe('1000');
});

it('hands order-placed listeners the discounted price', function (): void {
    Coupon::factory()->fixed(250, 'EUR')->active()->create(['code' => 'ONCE', 'max_usage' => 1]);
    $seen = null;

    Event::listen(function (OrderPlaced $event) use (&$seen): void {
        $seen = $event->order->price->getFinalPrice()->minor();
    });

    placeDiscountedOrder('ONCE');

    expect($seen)->toBe('750');
});

it('places the order without the coupon when a racing checkout used it up first', function (): void {
    $coupon = Coupon::factory()->percentage(1000)->active()->create(['code' => 'LAST', 'max_usage' => 1]);

    // Another checkout redeems the last use after this one checked the coupon but before it
    // redeemed it — the window between the unlocked eligibility check and the locked redemption.
    app()->bind(DiscountResolver::class, fn (): DiscountResolver => new class implements DiscountResolver
    {
        public function resolve(string $code, Money $goods): DiscountResult
        {
            $result = app(CouponPackageDiscountResolver::class)->resolve($code, $goods);
            Coupon::query()->where('code', $code)->firstOrFail()->redeemBy(null, $goods);

            return $result;
        }
    });

    $order = placeDiscountedOrder('LAST');

    // A coupon that turns out not to be redeemable is skipped — the order is placed at the
    // full price with no discount snapshot, never half-discounted, and checkout goes through.
    expect($order->exists)->toBeTrue()
        ->and($order->discount)->toBeNull()
        ->and($order->coupon_code)->toBeNull()
        ->and($order->coupon_id)->toBeNull()
        ->and($order->price->getFinalPrice()->minor())->toBe('1000')
        ->and($coupon->refresh()->usage)->toBe(1);
});
