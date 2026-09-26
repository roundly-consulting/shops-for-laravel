<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Products\Product;
use RoundlyConsulting\Shops\Shops\CurrentShop;
use RoundlyConsulting\Shops\Shops\Shop;
use RoundlyConsulting\Shops\Tests\Fixtures\CustomShop;

/**
 * The model-swap proof (S) for shops' tenant seam, driven through the REAL flow.
 *
 * This is shops' bug #3 as a test: a hard-coded call site sitting beside an honoured
 * config. `Shop::resolveModelClass()` returning the right class-string proves only
 * that the seam reads config — it says nothing about whether the flows that create
 * and hydrate tenants actually go through it. So this exercises the owner relation,
 * the current-shop context and a real create, and pins the CONCRETE class of every
 * model that comes back: `instanceof` would pass even for a row created as the
 * packaged Shop, which never fires the host's model events (#31).
 */
it('honours a host tenant model through the whole shop flow', function (): void {
    expect('shops.shop_model')->toHonourModelSwap(CustomShop::class, function (): array {
        $shop = CustomShop::query()->create(['name' => ['en' => 'Acme'], 'slug' => ['en' => 'acme']]);

        app(CurrentShop::class)->set($shop);

        $product = Product::factory()->create();

        return [
            $shop,
            // The owner relation hydrates through the seam...
            $product->shop,
            // ...as does the current-shop context, both ways of reaching it.
            Shop::current(),
            app(CurrentShop::class)->get(),
        ];
    });
});

it('generates a slug on a host tenant model through the inherited sluggable trait', function (): void {
    $shop = CustomShop::query()->create(['name' => ['en' => 'Acme EU']]);

    expect($shop->slugMap())->toBe(['en' => 'acme-eu'])
        ->and($shop->getRouteKeyName())->toBe('slug')
        ->and(CustomShop::findBySlug('acme-eu')?->is($shop))->toBeTrue();
});

// The structural half of both seams — non-final, and the config default really points
// at the packaged model — is pinned once in tests/ArchTest.php by
// `ArchPresets::swappableModelsAreNotFinal()`. It deliberately does NOT live here:
// that preset asserts the config *default*, which this file has swapped away.
