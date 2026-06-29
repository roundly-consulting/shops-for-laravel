<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Products;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Attributes\Contracts\HasAttributes as HasAttributesContract;
use RoundlyConsulting\Attributes\Traits\HasAttributes;
use RoundlyConsulting\MediaLibrary\Contracts\HasMedia;
use RoundlyConsulting\Reviews\Concerns\HasReviews;
use RoundlyConsulting\Reviews\Support\PendingReview;
use RoundlyConsulting\Shops\Concerns\BelongsToShop;
use RoundlyConsulting\Shops\Concerns\HasPublishing;
use RoundlyConsulting\Shops\Concerns\HasSlug;
use RoundlyConsulting\Shops\Concerns\HasTranslations;
use RoundlyConsulting\Shops\Contracts\Translatable;
use RoundlyConsulting\Shops\Database\Factories\ProductFactory;
use RoundlyConsulting\Shops\Products\Concerns\BelongsToManyCategories;
use RoundlyConsulting\Shops\Products\Concerns\HasOptions;
use RoundlyConsulting\Shops\Products\Concerns\HasProductMedia;
use RoundlyConsulting\Shops\Products\Concerns\HasVariants;
use RoundlyConsulting\Shops\Reviews\Contracts\VerifiedPurchaseResolver;
use RoundlyConsulting\Shops\Support\Money\Money;

/**
 * @property int|null $shop_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property-read Money $price
 * @property CarbonInterface|null $published_at
 * @property-read Collection<int, ProductVariant> $variants
 * @property-read ProductVariant|null $defaultVariant
 * @property-read Collection<int, ProductOption> $options
 */
final class Product extends Model implements HasAttributesContract, HasMedia, Translatable
{
    use BelongsToManyCategories;
    use BelongsToShop;
    use HasAttributes;
    /** @use HasFactory<ProductFactory> */
    use HasFactory;
    use HasOptions;
    use HasProductMedia;
    use HasPublishing;
    use HasReviews;
    use HasSlug;
    use HasTranslations;
    use HasVariants;
    use SoftDeletes;

    protected $table = 'products';

    protected $guarded = [];

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    /**
     * Begin a review for this product by the given author, pre-flagged as a
     * verified purchase according to the configured resolver. Chain the fluent
     * setters (rating/title/content/...) and call create().
     */
    public function review(Model $author): PendingReview
    {
        $verified = app(VerifiedPurchaseResolver::class)->verified($author, $this);

        return $this->addReview($author)->verified($verified);
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
