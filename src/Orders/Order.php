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
use RoundlyConsulting\Shops\Orders\Actions\TransitionOrderStatusAction;
use RoundlyConsulting\Shops\Orders\Concerns\HasCoupon;
use RoundlyConsulting\Shops\Orders\Concerns\HasItems;
use RoundlyConsulting\Shops\Orders\Concerns\HasNumber;
use RoundlyConsulting\Shops\Orders\Concerns\HasPrice;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Address;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Price;
use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Support\Casts\AddressCast;

/**
 * @property int $id
 * @property string $number
 * @property Status $status
 * @property int|null $coupon_id
 * @property int|null $shop_id
 * @property string|null $note
 * @property Address|null $billing_address
 * @property Address|null $shipping_address
 * @property CarbonInterface|null $in_progress_at
 * @property CarbonInterface|null $paid_at
 * @property CarbonInterface|null $fulfilled_at
 * @property CarbonInterface|null $canceled_at
 * @property CarbonInterface|null $refunded_at
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
            'billing_address' => AddressCast::class,
            'shipping_address' => AddressCast::class,
            'in_progress_at' => 'datetime',
            'paid_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'canceled_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function transitionTo(Status $status): self
    {
        return resolve(TransitionOrderStatusAction::class)->execute($this, $status);
    }

    public function markInProgress(): self
    {
        return $this->transitionTo(Status::InProgress);
    }

    public function markPaid(): self
    {
        return $this->transitionTo(Status::Paid);
    }

    public function markFulfilled(): self
    {
        return $this->transitionTo(Status::Fulfilled);
    }

    public function cancel(): self
    {
        return $this->transitionTo(Status::Canceled);
    }

    public function refund(): self
    {
        return $this->transitionTo(Status::Refunded);
    }
}
