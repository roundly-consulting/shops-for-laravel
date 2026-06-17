<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Shops\Database\Factories\StockAdjustmentFactory;
use RoundlyConsulting\Shops\Inventory\Enums\StockReason;
use RoundlyConsulting\Shops\Products\ProductVariant;

/**
 * @property int $id
 * @property int $product_variant_id
 * @property int $quantity
 * @property StockReason $reason
 * @property string|null $note
 * @property-read ProductVariant $variant
 */
final class StockAdjustment extends Model
{
    /** @use HasFactory<StockAdjustmentFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'stock_adjustments';

    protected $guarded = [];

    protected static function newFactory(): StockAdjustmentFactory
    {
        return StockAdjustmentFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'int',
            'reason' => StockReason::class,
        ];
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
