<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Cart;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Shops\Database\Factories\CartItemFactory;
use RoundlyConsulting\Shops\Products\ProductVariant;
use RoundlyConsulting\Shops\Support\Casts\MoneyCast;
use RoundlyConsulting\Shops\Support\Money\Money;

/**
 * @property int $id
 * @property int $cart_id
 * @property int|null $product_variant_id
 * @property string $name
 * @property string|null $sku
 * @property int $quantity
 * @property Money $price
 * @property string $currency
 * @property string $tax_class
 * @property-read Cart $cart
 * @property-read ProductVariant|null $variant
 */
final class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'cart_items';

    protected $guarded = [];

    protected static function newFactory(): CartItemFactory
    {
        return CartItemFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'int',
            'price' => MoneyCast::class,
        ];
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
