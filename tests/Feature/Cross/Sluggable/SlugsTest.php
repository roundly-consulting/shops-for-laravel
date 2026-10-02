<?php

declare(strict_types=1);

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RoundlyConsulting\Shops\Products\Category;
use RoundlyConsulting\Shops\Products\Product;
use RoundlyConsulting\Shops\Shops\CurrentShop;
use RoundlyConsulting\Shops\Shops\Shop;
use RoundlyConsulting\Sluggable\Schema\SlugIndexes;

/**
 * Shop, product and category slugs through sluggable-for-laravel: per-locale maps,
 * uniqueness per shop (products, categories) or across shops (shops), engine-enforced
 * indexes, locale-aware route binding incl. `->scopeBindings()`, and a deterministic
 * default-variant SKU.
 */
beforeEach(function (): void {
    config()->set('shops.locales.fallback', 'en');
    app()->setLocale('en');

    Route::middleware(SubstituteBindings::class)->group(function (): void {
        Route::get('/shops/{shop}', fn (Shop $shop) => 'shop '.$shop->getKey());
        Route::get('/products/{product}', fn (Product $product) => 'product '.$product->getKey());
        Route::get('/shops/{shop}/products/{product}', fn (Shop $shop, Product $product) => 'scoped '.$product->getKey())
            ->scopeBindings();
        Route::get('/shops/{shop}/categories/{category}', fn (Shop $shop, Category $category) => 'category '.$category->getKey())
            ->scopeBindings();
    });
});

/**
 * @param  array<string, string>|string  $name
 */
function slugProduct(Shop $shop, array|string $name): Product
{
    return Product::factory()->create(['shop_id' => $shop->getKey(), 'name' => $name]);
}

it('suffixes a same-name product within one shop', function (): void {
    $shop = Shop::factory()->create();

    $first = slugProduct($shop, 'Chair');
    $second = slugProduct($shop, 'Chair');

    expect($first->slugMap())->toBe(['en' => 'chair'])
        ->and($second->slugMap())->toBe(['en' => 'chair-2']);
});

it('lets two shops use the same product slug', function (): void {
    $a = slugProduct(Shop::factory()->create(), 'Chair');
    $b = slugProduct(Shop::factory()->create(), 'Chair');

    expect($a->currentSlug())->toBe('chair')
        ->and($b->currentSlug())->toBe('chair');
});

it('scopes category slugs per shop too', function (): void {
    $shop = Shop::factory()->create();
    $other = Shop::factory()->create();

    $first = Category::factory()->create(['shop_id' => $shop->getKey(), 'name' => 'Chairs']);
    $second = Category::factory()->create(['shop_id' => $shop->getKey(), 'name' => 'Chairs']);
    $elsewhere = Category::factory()->create(['shop_id' => $other->getKey(), 'name' => 'Chairs']);

    expect($first->currentSlug())->toBe('chairs')
        ->and($second->currentSlug())->toBe('chairs-2')
        ->and($elsewhere->currentSlug())->toBe('chairs');
});

it('keeps shop slugs unique across every shop', function (): void {
    $first = Shop::create(['name' => 'Acme']);
    $second = Shop::create(['name' => 'Acme']);

    expect($first->currentSlug())->toBe('acme')
        ->and($second->currentSlug())->toBe('acme-2');
});

it('fills shop_id from the current shop before the scoped uniqueness check', function (): void {
    $shop = Shop::factory()->create();
    $other = Shop::factory()->create();

    app(CurrentShop::class)->set($shop);
    $first = Product::factory()->create(['name' => 'Chair']);
    $second = Product::factory()->create(['name' => 'Chair']);

    app(CurrentShop::class)->set($other);
    $elsewhere = Product::factory()->create(['name' => 'Chair']);

    expect($first->shop_id)->toBe($shop->getKey())
        ->and($first->currentSlug())->toBe('chair')
        ->and($second->currentSlug())->toBe('chair-2')
        ->and($elsewhere->shop_id)->toBe($other->getKey())
        ->and($elsewhere->currentSlug())->toBe('chair');
});

it('generates every locale from the raw name map, not the current-locale read', function (): void {
    $product = slugProduct(Shop::factory()->create(), ['en' => 'Chair', 'sk' => 'Stolička']);

    expect($product->getTranslations('slug'))->toBe(['en' => 'chair', 'sk' => 'stolicka'])
        ->and($product->fresh()?->slugFor('sk'))->toBe('stolicka');
});

it('keeps uniqueness independent per locale', function (): void {
    $shop = Shop::factory()->create();

    $chair = slugProduct($shop, ['en' => 'Chair', 'sk' => 'Stolička']);
    $stool = slugProduct($shop, ['en' => 'Stool', 'sk' => 'Stolička']);
    $crossLocale = slugProduct($shop, ['en' => 'Stolicka']);

    expect($stool->slugMap())->toBe(['en' => 'stool', 'sk' => 'stolicka-2'])
        // `stolicka` is taken in sk only — en is its own namespace.
        ->and($crossLocale->slugMap())->toBe(['en' => 'stolicka'])
        ->and($chair->slugMap())->toBe(['en' => 'chair', 'sk' => 'stolicka']);
});

it('normalises and uniquifies a manual slug', function (): void {
    $shop = Shop::factory()->create();
    slugProduct($shop, 'Custom Slug');

    $product = Product::factory()->create(['shop_id' => $shop->getKey(), 'name' => 'Anything', 'slug' => 'Custom Slug!']);

    expect($product->currentSlug())->toBe('custom-slug-2');
});

it('fills a missing locale on update without touching the existing one', function (): void {
    $product = slugProduct(Shop::factory()->create(), 'Chair');

    $product->setTranslation('name', 'sk', 'Stolička')->save();

    expect($product->fresh()?->slugMap())->toBe(['en' => 'chair', 'sk' => 'stolicka']);
});

it('gives an empty name a random slug instead of an empty one', function (): void {
    $product = slugProduct(Shop::factory()->create(), '');

    expect($product->currentSlug())->toBeString()->not->toBe('');
});

it('keeps a trashed product blocking its slug in its shop', function (): void {
    $shop = Shop::factory()->create();
    slugProduct($shop, 'Chair')->delete();

    expect(slugProduct($shop, 'Chair')->currentSlug())->toBe('chair-2');
});

it('binds a product by its slug in the request locale', function (): void {
    $product = slugProduct(Shop::factory()->create(), ['en' => 'Chair', 'sk' => 'Stolička']);

    app()->setLocale('sk');

    $this->get('/products/stolicka')->assertOk()->assertSee('product '.$product->getKey());
    // The fallback locale still resolves under sk.
    $this->get('/products/chair')->assertOk()->assertSee('product '.$product->getKey());
    $this->get('/products/nope')->assertNotFound();

    expect($product->getRouteKey())->toBe('stolicka');
});

it('binds a shop by its slug', function (): void {
    $shop = Shop::create(['name' => 'Acme EU']);

    $this->get('/shops/acme-eu')->assertOk()->assertSee('shop '.$shop->getKey());
});

it('scopes product binding to the parent shop', function (): void {
    $acme = Shop::create(['name' => 'Acme']);
    $globex = Shop::create(['name' => 'Globex']);
    $product = slugProduct($acme, 'Chair');
    slugProduct($globex, 'Table');

    $this->get('/shops/acme/products/chair')->assertOk()->assertSee('scoped '.$product->getKey());
    $this->get('/shops/globex/products/chair')->assertNotFound();
});

it('scopes category binding to the parent shop', function (): void {
    $acme = Shop::create(['name' => 'Acme']);
    Shop::create(['name' => 'Globex']);
    $category = Category::factory()->create(['shop_id' => $acme->getKey(), 'name' => 'Chairs']);

    $this->get('/shops/acme/categories/chairs')->assertOk()->assertSee('category '.$category->getKey());
    $this->get('/shops/globex/categories/chairs')->assertNotFound();
});

it('redirects a retired slug when shops.slugs.history is on', function (): void {
    config()->set('shops.slugs.history', true);

    $product = slugProduct(Shop::factory()->create(), 'Chair');
    $product->setTranslation('slug', 'en', 'armchair')->save();

    $this->get('/products/chair')->assertStatus(301)->assertRedirect('/products/armchair');
    $this->get('/products/armchair')->assertOk();
});

it('keeps no history by default', function (): void {
    $product = slugProduct(Shop::factory()->create(), 'Chair');
    $product->setTranslation('slug', 'en', 'armchair')->save();

    $this->get('/products/chair')->assertNotFound();
});

it('derives the same default SKU whatever the request locale', function (): void {
    $name = ['en' => 'Chair', 'sk' => 'Stolička'];

    app()->setLocale('sk');
    $underSk = slugProduct(Shop::factory()->create(), $name);

    app()->setLocale('en');
    $underEn = slugProduct(Shop::factory()->create(), $name);

    expect($underSk->defaultVariant?->sku)->toBe('CHAIR-DEFAULT')
        ->and($underEn->defaultVariant?->sku)->toBe('CHAIR-DEFAULT');
});

it('falls back to the current-locale slug for the SKU when the fallback locale is absent', function (): void {
    app()->setLocale('sk');

    $product = slugProduct(Shop::factory()->create(), ['sk' => 'Stolička']);

    expect($product->defaultVariant?->sku)->toBe('STOLICKA-DEFAULT');
});

it('enforces per-shop product slugs in the database', function (): void {
    $shop = Shop::factory()->create();
    $other = Shop::factory()->create();
    $row = static fn (int $shopId): array => [
        'shop_id' => $shopId,
        'name' => json_encode(['en' => 'Chair']),
        'slug' => json_encode(['en' => 'chair']),
    ];

    DB::table('products')->insert($row($shop->getKey()));
    DB::table('products')->insert($row($other->getKey()));

    expect(DB::table('products')->count())->toBe(2)
        ->and(fn () => DB::table('products')->insert($row($shop->getKey())))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('enforces per-shop category slugs per indexed locale in the database', function (): void {
    $shop = Shop::factory()->create();
    $row = static fn (string $en): array => [
        'shop_id' => $shop->getKey(),
        'name' => json_encode(['en' => $en]),
        'slug' => json_encode(['en' => $en, 'sk' => 'stolicky']),
    ];

    DB::table('product_categories')->insert($row('chairs'));

    expect(fn () => DB::table('product_categories')->insert($row('seats')))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('enforces global shop slugs in the database', function (): void {
    $row = ['name' => json_encode(['en' => 'Acme']), 'slug' => json_encode(['en' => 'acme'])];

    DB::table('shops')->insert($row);

    expect(fn () => DB::table('shops')->insert($row))->toThrow(UniqueConstraintViolationException::class);
});

/**
 * The migrations hand-write their index specs; this pins them to the model definitions.
 * `forModel()` derives the specs from `slugOptions()` — every index it wants must
 * already exist (skipped), one per supported locale, and nothing may be left to create.
 */
it('ships exactly the slug indexes the model definitions ask for', function (string $model, string $table): void {
    $report = SlugIndexes::forModel($model, dryRun: true);

    expect($report->created)->toBe([])
        ->and($report->skipped)->toBe(["{$table}_slug_en_slug_unique", "{$table}_slug_sk_slug_unique"]);
})->with([
    'shops' => [Shop::class, 'shops'],
    'products' => [Product::class, 'products'],
    'categories' => [Category::class, 'product_categories'],
]);

it('reads an env-string history switch as a boolean', function (string $value, int $status): void {
    config()->set('shops.slugs.history', $value);

    $product = slugProduct(Shop::factory()->create(), 'Chair');
    $product->setTranslation('slug', 'en', 'armchair')->save();

    $this->get('/products/chair')->assertStatus($status);
})->with([
    'on' => ['on', 301],
    '1' => ['1', 301],
    'off' => ['off', 404],
    '0' => ['0', 404],
]);
