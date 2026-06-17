<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Products;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Shops\Database\Factories\ProductOptionFactory;

/**
 * @property int $id
 * @property int $product_id
 * @property string $name
 * @property int $position
 * @property-read Product $product
 * @property-read Collection<int, ProductOptionValue> $values
 */
final class ProductOption extends Model
{
    /** @use HasFactory<ProductOptionFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'product_options';

    protected $guarded = [];

    protected static function newFactory(): ProductOptionFactory
    {
        return ProductOptionFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'int',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<ProductOptionValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(ProductOptionValue::class, 'product_option_id')->orderBy('position');
    }
}
