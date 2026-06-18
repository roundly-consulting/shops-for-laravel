<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RoundlyConsulting\Shops\Shops\Shop;
use RoundlyConsulting\Shops\Shops\TaxRate;
use RoundlyConsulting\Shops\Support\Tax\TaxRateValue;

it('creates a rate with sane factory defaults', function (): void {
    $rate = TaxRate::factory()->for(Shop::factory(), 'shop')->create();

    expect($rate->tax_class)->toBe('standard')
        ->and($rate->rate)->toBe(2000)
        ->and($rate->is_default)->toBeFalse()
        ->and($rate->priority)->toBe(0);
});

it('applies the default, reduced and forCountry states', function (): void {
    $default = TaxRate::factory()->default()->make();
    $reduced = TaxRate::factory()->reduced()->make();
    $german = TaxRate::factory()->forCountry('de')->make();

    expect($default->is_default)->toBeTrue()
        ->and($reduced->tax_class)->toBe('reduced')
        ->and($reduced->rate)->toBe(1000)
        ->and($german->country)->toBe('DE');
});

it('casts rate and priority to int and is_default to bool', function (): void {
    $rate = TaxRate::factory()->for(Shop::factory(), 'shop')->create([
        'rate' => '1900',
        'priority' => '5',
        'is_default' => 1,
    ]);

    expect($rate->refresh()->rate)->toBe(1900)
        ->and($rate->priority)->toBe(5)
        ->and($rate->is_default)->toBeTrue();
});

it('resolves its owning shop through the belongs-to relation', function (): void {
    $shop = Shop::factory()->create();
    $rate = TaxRate::factory()->for($shop, 'shop')->create();

    expect($rate->shop())->toBeInstanceOf(BelongsTo::class)
        ->and($rate->shop->is($shop))->toBeTrue();
});

it('converts to a tax rate value object', function (): void {
    $rate = TaxRate::factory()->for(Shop::factory(), 'shop')->create([
        'name' => 'Germany Standard',
        'tax_class' => 'standard',
        'country' => 'DE',
        'rate' => 1900,
    ]);

    $value = $rate->toValue();

    expect($value)->toBeInstanceOf(TaxRateValue::class)
        ->and($value->basisPoints)->toBe(1900)
        ->and($value->taxClass)->toBe('standard')
        ->and($value->country)->toBe('DE')
        ->and($value->label)->toBe('Germany Standard');
});

it('cascades rate deletion when its shop is deleted', function (): void {
    $shop = Shop::factory()->create();
    TaxRate::factory()->for($shop, 'shop')->create();

    $shop->forceDelete();

    expect(TaxRate::query()->where('shop_id', $shop->id)->exists())->toBeFalse();
});
