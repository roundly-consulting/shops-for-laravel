<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Shops;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Shops\Concerns\BelongsToShop;
use RoundlyConsulting\Shops\Concerns\HasSlug;
use RoundlyConsulting\Shops\Concerns\HasTranslations;
use RoundlyConsulting\Shops\Contracts\Translatable;
use RoundlyConsulting\Shops\Database\Factories\ShopFactory;

/**
 * The concrete tenant a shop-owned record belongs to. Every owned model
 * (products, categories, coupons, carts, orders, items, tax rates) carries a
 * plain `shop_id` foreign key pointing at this table.
 *
 * The backing class is swappable via `config('shops.shop_model')`; relations on
 * {@see BelongsToShop} resolve through
 * {@see Shop::resolveModelClass()} so a host can ship its own tenant model.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $currency
 * @property-read Collection<int, TaxRate> $taxRates
 */
final class Shop extends Model implements Translatable
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
     * @return HasMany<TaxRate, $this>
     */
    public function taxRates(): HasMany
    {
        return $this->hasMany(TaxRate::class, 'shop_id');
    }

    /**
     * This shop's ISO-4217 currency, falling back to the package default when no
     * per-shop override is set.
     */
    public function currency(): string
    {
        /** @var string|null $currency */
        $currency = $this->getAttribute('currency');

        return $currency ?? (string) config('shops.pricing.default_currency', 'EUR');
    }

    /**
     * The configured tenant model class. Defaults to this model, but a host may
     * point `shops.shop_model` at its own implementation.
     *
     * @return class-string<Model>
     */
    public static function resolveModelClass(): string
    {
        /** @var class-string<Model> $class */
        $class = config('shops.shop_model', self::class);

        return $class;
    }

    /**
     * The shop bound to the current container context, if any.
     */
    public static function current(): ?self
    {
        $shop = app(CurrentShop::class)->get();

        return $shop instanceof self ? $shop : null;
    }
}
