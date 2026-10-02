<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Shops\Facades\Shops;
use RoundlyConsulting\Shops\Orders\Item;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Products\Product;
use RoundlyConsulting\Shops\Tests\Fixtures\Customer;

/**
 * Every shops switch can be fed by env(), which only turns `true`/`false` into booleans —
 * `SHOPS_REFUND_TO_STORE_CREDIT=on` or `=1` arrives as a string. Each switch is read through
 * the toolkit's Config::boolean, so behaviour and the `about` row agree on what it means.
 */
function envBoolBuyerWithPaidOrder(): array
{
    $customer = Customer::create(['name' => 'Ada']);
    $order = Order::factory()->paid()->create();
    $order->customer()->associate($customer)->save();
    Item::factory()->for($order)->withEurPrice('1000')->state(['quantity' => 1, 'tax_class' => 'zero'])->create();

    return [$order->refresh(), $customer];
}

/**
 * @return array<string, string>
 */
function shopsAboutRows(): array
{
    Artisan::call('about', ['--only' => 'shops', '--json' => true]);

    /** @var array{shops: array<string, string>} $about */
    $about = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    return $about['shops'];
}

it('reads the refund-to-store-credit switch as a boolean', function (string $value, int $granted): void {
    config()->set('shops.payment.refund_to_store_credit', $value);

    [$order, $customer] = envBoolBuyerWithPaidOrder();

    $order->refund();

    expect($customer->creditsBalance(bucket: 'store_credit'))->toBe($granted);
})->with([
    'on' => ['on', 1000],
    '1' => ['1', 1000],
    'off' => ['off', 0],
    '0' => ['0', 0],
]);

it('reads the store-credit tender switch as a boolean', function (string $value, int $left): void {
    config()->set('shops.payment.allow_store_credit', $value);

    $customer = Customer::create(['name' => 'Ada']);
    $customer->modifyCredits(500, bucket: 'store_credit');
    $order = Order::factory()->create();
    $order->customer()->associate($customer)->save();
    Item::factory()->for($order)->withEurPrice('1000')->state(['quantity' => 1, 'tax_class' => 'zero'])->create();

    Shops::order($order->refresh())->charge();

    expect($customer->creditsBalance(bucket: 'store_credit'))->toBe($left);
})->with([
    'yes' => ['yes', 0],
    '1' => ['1', 0],
    'off' => ['off', 500],
    'no' => ['no', 500],
]);

it('reads the billing-same-as-shipping switch as a boolean', function (string $value, bool $copied): void {
    config()->set('shops.addresses.billing_same_as_shipping', $value);

    $customer = Customer::create(['name' => 'Ada']);
    $customer->addAddress(AddressData::make(
        city: 'London', street: '1 Shipping Way', postalCode: 'EC1', countryIso: 'GB',
        name: 'Ada Ship', type: AddressType::Shipping, isPrimary: true,
    ));

    expect(Shops::addresses()->defaults($customer)->billing !== null)->toBe($copied);
})->with([
    'on' => ['on', true],
    'off' => ['off', false],
    '0' => ['0', false],
]);

it('reads the media visibility switch as a boolean', function (string $value, string $visibility): void {
    config()->set('shops.media.public', $value);

    $product = Product::factory()->create();
    $product->addMedia(UploadedFile::fake()->image('hero.jpg', 64, 64))
        ->toMediaBucket($product->featuredBucket());

    expect($product->featuredImage()?->visibility)->toBe($visibility);
})->with([
    'off' => ['off', 'private'],
    '0' => ['0', 'private'],
    'on' => ['on', 'public'],
]);

it('reports the switches in about exactly as behaviour reads them', function (): void {
    config()->set('shops.payment.allow_store_credit', '1');
    config()->set('shops.payment.refund_to_store_credit', 'off');
    config()->set('shops.media.public', 'off');
    config()->set('shops.slugs.history', 'off');

    $rows = shopsAboutRows();

    expect($rows['store_credit'])->toBe('ON (bucket SET, refunds OFF)')
        ->and($rows['catalog_media'])->toContain('private')
        ->and($rows['slug_history'])->toBe('OFF');
});
