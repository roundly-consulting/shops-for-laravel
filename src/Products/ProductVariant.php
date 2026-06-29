<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Products;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\MediaLibrary\Contracts\HasMedia;
use RoundlyConsulting\Shops\Database\Factories\ProductVariantFactory;
use RoundlyConsulting\Shops\Products\Concerns\HasVariantMedia;
use RoundlyConsulting\Shops\Support\Casts\MoneyCast;
use RoundlyConsulting\Shops\Support\Money\Money;

/**
 * @property int $id
 * @property int $product_id
 * @property string $sku
 * @property string|null $name
 * @property Money $price
 * @property string $currency
 * @property string $tax_class
 * @property bool $track_stock
 * @property int $stock
 * @property int $reserved
 * @property int $position
 * @property-read Product $product
 */
final class ProductVariant extends Model implements HasMedia
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;
    use HasVariantMedia;
    use SoftDeletes;

    protected $table = 'product_variants';

    protected $guarded = [];

    protected static function newFactory(): ProductVariantFactory
    {
        return ProductVariantFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => MoneyCast::class,
            'track_stock' => 'bool',
            'stock' => 'int',
            'reserved' => 'int',
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
     * @return BelongsToMany<ProductOptionValue, $this>
     */
    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductOptionValue::class,
            'product_variant_option_value',
            'product_variant_id',
            'product_option_value_id',
        );
    }

    /**
     * Available quantity is the on-hand stock minus what is reserved for
     * pending orders.
     */
    public function availableStock(): int
    {
        return $this->stock - $this->reserved;
    }

    public function inStock(int $quantity = 1): bool
    {
        if (! $this->track_stock) {
            return true;
        }

        return $this->availableStock() >= $quantity;
    }

    /**
     * @param  Builder<ProductVariant>  $query
     */
    public function scopeInStock(Builder $query, int $quantity = 1): void
    {
        $query->where(function (Builder $query) use ($quantity): void {
            $query->where('track_stock', false)
                ->orWhereRaw('stock - reserved >= ?', [$quantity]);
        });
    }
}
