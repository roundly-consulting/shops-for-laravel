<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Shops;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\Shops\Concerns\BelongsToShop;
use RoundlyConsulting\Shops\Concerns\HasTranslations;
use RoundlyConsulting\Shops\Contracts\Translatable;
use RoundlyConsulting\Shops\Database\Factories\ShopFactory;
use RoundlyConsulting\Shops\Products\Category;
use RoundlyConsulting\Shops\Products\Product;
use RoundlyConsulting\Shops\ShopsManager;
use RoundlyConsulting\Shops\Support\ShopModel;
use RoundlyConsulting\Sluggable\Concerns\HasSlug;
use RoundlyConsulting\Sluggable\Contracts\Sluggable;
use RoundlyConsulting\Sluggable\Definitions\SlugDefinition;
use RoundlyConsulting\Sluggable\Definitions\SlugOptions;

/**
 * The concrete tenant a shop-owned record belongs to. Every owned model
 * (products, categories, coupons, carts, orders, items, tax rates) carries a
 * plain `shop_id` foreign key pointing at this table.
 *
 * The backing class is swappable via `config('shops.shop_model')`; relations on
 * {@see BelongsToShop} resolve through
 * {@see Shop::resolveModelClass()} so a host can ship its own tenant model.
 *
 * Not `final` on purpose: `shops.shop_model` documents extending this model, and
 * `Shop::current()` only hands back a shop it recognises — both of which a final
 * class makes impossible.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $currency
 * @property-read Collection<int, TaxRate> $taxRates
 * @property-read Collection<int, Product> $products
 * @property-read Collection<int, Category> $categories
 */
class Shop extends Model implements Sluggable, Translatable
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory;
    use HasSlug;
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'shops';

    protected $guarded = [];

    protected static function newFactory(): ShopFactory
    {
        return ShopFactory::new();
    }

    /**
     * @return list<string>
     */
    public function translatableAttributes(): array
    {
        return ['name', 'slug'];
    }

    /**
     * One locale-map slug from `name`, unique across all shops per locale, and the
     * route key — `/shops/{shop}` binds by the current-locale slug.
     */
    public function slugOptions(): SlugOptions
    {
        return SlugOptions::make(
            SlugDefinition::for('slug')
                ->from('name')
                ->localized()
                ->unique()
                ->fallbackLocale(fn (): string => (string) config('shops.locales.fallback', 'en'))
                ->keepHistory(Config::boolean('shops.slugs.history'))
                ->routeKey(),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'slug' => 'array',
        ];
    }

    /**
     * The shop's catalog. Also what `->scopeBindings()` walks for
     * `/shops/{shop}/products/{product}`.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'shop_id');
    }

    /**
     * The shop's categories; the scoped-binding relation for
     * `/shops/{shop}/categories/{category}`.
     *
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'shop_id');
    }

    /**
     * @return HasMany<TaxRate, $this>
     */
    public function taxRates(): HasMany
    {
        return $this->hasMany(TaxRate::class, 'shop_id');
    }

    /**
     * This shop's currency (resolved through money's registry), falling back to the
     * package default when no per-shop override is set.
     */
    public function currency(): Currency
    {
        /** @var string|null $currency */
        $currency = $this->getAttribute('currency');

        return Currency::of($currency ?? (string) config('shops.pricing.default_currency', 'EUR'));
    }

    /**
     * The configured tenant model class. Defaults to this model, but a host may
     * point `shops.shop_model` at its own implementation.
     *
     * @return class-string<Model>
     */
    public static function resolveModelClass(): string
    {
        return ShopModel::class();
    }

    /**
     * The shop bound to the current container context, if any.
     */
    public static function current(): ?self
    {
        $shop = app(ShopsManager::class)->current()->get();

        return $shop instanceof self ? $shop : null;
    }
}
