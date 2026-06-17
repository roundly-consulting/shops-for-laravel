<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Products\Product;

beforeEach(function (): void {
    config()->set('shops.locales.fallback', 'en');
});

it('stores and reads a translation for the current locale', function (): void {
    $product = Product::factory()->create();
    $product->setTranslation('name', 'en', 'Hat');
    $product->setTranslation('name', 'sk', 'Klobúk');
    $product->save();

    app()->setLocale('en');
    expect($product->fresh()->name)->toBe('Hat');

    app()->setLocale('sk');
    expect($product->fresh()->name)->toBe('Klobúk');
});

it('falls back to the fallback locale when the current locale is missing', function (): void {
    $product = Product::factory()->create();
    $product->setTranslation('name', 'en', 'Hat')->save();

    app()->setLocale('de');

    expect($product->fresh()->name)->toBe('Hat');
});

it('returns null when no translation exists at all', function (): void {
    $product = Product::factory()->create();
    $product->setAttribute('description', null);

    app()->setLocale('fr');

    expect($product->getTranslation('description', 'fr'))->toBeNull();
});

it('writes a plain string into the current locale key', function (): void {
    app()->setLocale('sk');
    $product = Product::factory()->create();
    $product->name = 'Stôl';
    $product->save();

    expect($product->fresh()->getTranslations('name'))->toBe(['sk' => 'Stôl']);
});

it('generates a slug per locale from the name translations', function (): void {
    $product = Product::factory()->make(['name' => null, 'slug' => null]);
    $product->setTranslation('name', 'en', 'Garden Chair');
    $product->setTranslation('name', 'sk', 'Záhradná Stolička');
    $product->save();

    expect($product->getTranslations('slug'))
        ->toBe(['en' => 'garden-chair', 'sk' => 'zahradna-stolicka']);
});

it('keeps an explicitly provided slug translation', function (): void {
    $product = Product::factory()->make(['name' => null, 'slug' => null]);
    $product->setTranslation('name', 'en', 'Garden Chair');
    $product->setTranslation('slug', 'en', 'custom-chair');
    $product->save();

    expect($product->getTranslation('slug', 'en'))->toBe('custom-chair');
});

it('exposes the translatable attribute list', function (): void {
    expect((new Product)->translatableAttributes())->toBe(['name', 'slug', 'description']);
});
