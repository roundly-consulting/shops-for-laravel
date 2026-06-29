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
use RoundlyConsulting\Shops\Concerns\HasSlug;
use RoundlyConsulting\Shops\Concerns\HasTranslations;
use RoundlyConsulting\Shops\Contracts\Translatable;
use RoundlyConsulting\Shops\Database\Factories\CategoryFactory;
use RoundlyConsulting\Shops\Products\Concerns\HasCategoryMedia;

/**
 * @property int|null $shop_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property CarbonInterface|null $published_at
 */
final class Category extends Model implements HasMedia, Translatable
{
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
