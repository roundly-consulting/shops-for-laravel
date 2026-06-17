<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Shops\Concerns\BelongsToShop;
use RoundlyConsulting\Shops\Database\Factories\OrderFactory;
use RoundlyConsulting\Shops\Orders\Concerns\HasCoupon;
use RoundlyConsulting\Shops\Orders\Concerns\HasItems;
use RoundlyConsulting\Shops\Orders\Concerns\HasNumber;
use RoundlyConsulting\Shops\Orders\Concerns\HasPrice;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Price;
use RoundlyConsulting\Shops\Orders\Enums\Status;

/**
 * @property int $id
 * @property string $number
 * @property Status $status
 * @property string|null $note
 * @property CarbonInterface|null $in_progress_at
 * @property CarbonInterface|null $completed_at
 * @property CarbonInterface|null $canceled_at
 * @property-read Price $price
 * @property-read Collection<int, Item> $items
 */
final class Order extends Model
{
    use BelongsToShop;
    use HasCoupon;
    /** @use HasFactory<OrderFactory> */
    use HasFactory;
    use HasItems;
    use HasNumber;
    use HasPrice;
    use SoftDeletes;

    protected $table = 'orders';

    protected $guarded = [];

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'in_progress_at' => 'datetime',
            'completed_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }
}
