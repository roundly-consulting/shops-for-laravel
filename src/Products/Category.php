<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Products;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\MediaLibrary\Contracts\HasMedia;
use RoundlyConsulting\Shops\Concerns\BelongsToShop;
use RoundlyConsulting\Shops\Concerns\HasPublishing;
use RoundlyConsulting\Shops\Concerns\HasTranslations;
use RoundlyConsulting\Shops\Contracts\Translatable;
use RoundlyConsulting\Shops\Database\Factories\CategoryFactory;
use RoundlyConsulting\Shops\Products\Concerns\HasCategoryMedia;
use RoundlyConsulting\Sluggable\Concerns\HasSlug;
use RoundlyConsulting\Sluggable\Contracts\Sluggable;
use RoundlyConsulting\Sluggable\Definitions\SlugDefinition;
use RoundlyConsulting\Sluggable\Definitions\SlugOptions;

/**
 * @property int|null $shop_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property CarbonInterface|null $published_at
 */
final class Category extends Model implements HasMedia, Sluggable, Translatable
{
    // Must stay above HasSlug: its `creating` listener fills shop_id before the
    // per-shop uniqueness probe runs.
    use BelongsToShop;
    use HasCategoryMedia;
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;
    use HasPublishing;
    use HasSlug;
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'product_categories';

    protected $guarded = [];

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }

    /**
     * @return list<string>
     */
    public function translatableAttributes(): array
    {
        return ['name', 'slug', 'description'];
    }

    /**
     * One locale-map slug from `name`, unique per shop and locale (two shops may both
     * use `chairs`), and the route key — so `/shops/{shop}/categories/{category}` works with
     * `->scopeBindings()`.
     */
    public function slugOptions(): SlugOptions
    {
        return SlugOptions::make(
            SlugDefinition::for('slug')
                ->from('name')
                ->localized()
                ->uniqueWithin('shop_id')
                ->fallbackLocale(fn (): string => (string) config('shops.locales.fallback', 'en'))
                ->keepHistory((bool) config('shops.slugs.history', false))
                ->routeKey(),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'name' => 'array',
            'slug' => 'array',
            'description' => 'array',
        ];
    }
}
