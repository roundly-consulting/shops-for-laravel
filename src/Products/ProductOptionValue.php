<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Products;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Shops\Database\Factories\ProductOptionValueFactory;

/**
 * @property int $id
 * @property int $product_option_id
 * @property string $value
 * @property int $position
 * @property-read ProductOption $option
 * @property-read Collection<int, ProductVariant> $variants
 */
final class ProductOptionValue extends Model
{
    /** @use HasFactory<ProductOptionValueFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'product_option_values';

    protected $guarded = [];

    protected static function newFactory(): ProductOptionValueFactory
    {
        return ProductOptionValueFactory::new();
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
     * @return BelongsTo<ProductOption, $this>
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id');
    }

    /**
     * @return BelongsToMany<ProductVariant, $this>
     */
    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductVariant::class,
            'product_variant_option_value',
            'product_option_value_id',
            'product_variant_id',
        );
    }
}
