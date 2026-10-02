<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Money\Casts\AsMoney;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Concerns\BelongsToShop;
use RoundlyConsulting\Shops\Concerns\HasQuantity;
use RoundlyConsulting\Shops\Database\Factories\ItemFactory;
use RoundlyConsulting\Shops\Orders\Concerns\BelongsToOrder;
use RoundlyConsulting\Shops\Orders\Concerns\BelongsToVariant;

/**
 * @property int $id
 * @property int|null $shop_id
 * @property int $order_id
 * @property int|null $product_id
 * @property int|null $product_variant_id
 * @property string $name
 * @property string|null $sku
 * @property int $quantity
 * @property Money $price
 * @property string $currency
 * @property string $tax_class
 * @property int|null $tax_rate
 * @property string|null $tax_label
 */
final class Item extends Model
{
    use BelongsToOrder;
    use BelongsToShop;
    use BelongsToVariant;
    /** @use HasFactory<ItemFactory> */
    use HasFactory;
    use HasQuantity;
    use SoftDeletes;

    protected $table = 'order_items';

    protected $guarded = [];

    protected static function newFactory(): ItemFactory
    {
        return ItemFactory::new();
    }

    /**
     * An item belongs to its order's shop: inherit the order's `shop_id` on insert unless one
     * was set explicitly. Done here, before the `creating` listeners run, so the order's shop
     * wins over a bound CurrentShop — and it holds under `Event::fake()` too.
     *
     * @param  Builder<static>  $query
     */
    protected function performInsert(Builder $query): bool
    {
        if ($this->getAttribute('shop_id') === null) {
            $order = $this->getRelationValue('order');

            if ($order instanceof Order && $order->shop_id !== null) {
                $this->setAttribute('shop_id', $order->shop_id);
            }
        }

        return parent::performInsert($query);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'int',
            'price' => AsMoney::currencyColumn('currency'),
            'tax_rate' => 'integer',
        ];
    }
}
