<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Shops\Concerns\BelongsToShop;
use RoundlyConsulting\Shops\Database\Factories\ItemFactory;
use RoundlyConsulting\Shops\Orders\Concerns\BelongsToOrder;
use RoundlyConsulting\Shops\Support\Casts\MoneyCast;
use RoundlyConsulting\Shops\Support\Money\Money;

/**
 * @property int $id
 * @property int $order_id
 * @property string $name
 * @property int $quantity
 * @property Money $price
 * @property string $currency
 */
final class Item extends Model
{
    use BelongsToOrder;
    use BelongsToShop;
    /** @use HasFactory<ItemFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'order_items';

    protected $guarded = [];

    protected static function newFactory(): ItemFactory
    {
        return ItemFactory::new();
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
}
