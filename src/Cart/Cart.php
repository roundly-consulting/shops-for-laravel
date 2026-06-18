<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Cart;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Shops\Cart\Concerns\HasOwner;
use RoundlyConsulting\Shops\Concerns\BelongsToShop;
use RoundlyConsulting\Shops\Contracts\Coupon;
use RoundlyConsulting\Shops\Database\Factories\CartFactory;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Price;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\PriceLine;
use RoundlyConsulting\Shops\Orders\Enums\PriceType;
use RoundlyConsulting\Shops\Products\ProductVariant;
use RoundlyConsulting\Shops\Shops\Shop;
use RoundlyConsulting\Shops\Support\Money\Money;

/**
 * @property int $id
 * @property string|null $token
 * @property int|null $shop_id
 * @property string $currency
 * @property string|null $coupon_code
 * @property-read Collection<int, CartItem> $items
 */
final class Cart extends Model
{
    use BelongsToShop;
    /** @use HasFactory<CartFactory> */
    use HasFactory;
    use HasOwner;
    use SoftDeletes;

    protected $table = 'carts';

    protected $guarded = [];

    protected static function newFactory(): CartFactory
    {
        return CartFactory::new();
    }

    /**
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Add a variant to the cart, snapshotting its price/sku and incrementing the
     * quantity when the same variant is already present.
     */
    public function add(ProductVariant $variant, int $quantity = 1): CartItem
    {
        $existing = $this->items()->where('product_variant_id', $variant->id)->first();

        if ($existing !== null) {
            $existing->increment('quantity', $quantity);

            return $existing->refresh();
        }

        $item = new CartItem([
            'product_variant_id' => $variant->id,
            'name' => $variant->name ?? $variant->product->name,
            'sku' => $variant->sku,
            'quantity' => $quantity,
            'price' => $variant->price,
            'currency' => $this->currency,
            'tax_class' => $variant->tax_class,
        ]);

        $this->items()->save($item);

        return $item;
    }

    public function subtotal(): Money
    {
        return $this->price()->getSubtotal();
    }

    public function price(?Coupon $coupon = null): Price
    {
        $lines = [];

        foreach ($this->items as $item) {
            $lines[] = new PriceLine(
                unitPrice: $item->price,
                quantity: $item->quantity,
                taxClass: $item->tax_class,
            );
        }

        $shop = $this->shop_id !== null && $this->shop instanceof Shop ? $this->shop : null;

        return new Price(
            lines: $lines,
            shipping: Money::zero($this->currency),
            priceType: PriceType::from((string) config('shops.pricing.price_type', 'gross')),
            taxResolver: null,
            coupon: $coupon,
            currency: $this->currency,
            shop: $shop,
        );
    }
}
