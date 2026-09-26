<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Money\Casts\AsCurrency;
use RoundlyConsulting\Money\Casts\AsMoney;
use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Concerns\BelongsToShop;
use RoundlyConsulting\Shops\Database\Factories\OrderFactory;
use RoundlyConsulting\Shops\Orders\Actions\TransitionOrderStatusAction;
use RoundlyConsulting\Shops\Orders\Concerns\HasCoupon;
use RoundlyConsulting\Shops\Orders\Concerns\HasCustomer;
use RoundlyConsulting\Shops\Orders\Concerns\HasItems;
use RoundlyConsulting\Shops\Orders\Concerns\HasNumber;
use RoundlyConsulting\Shops\Orders\Concerns\HasPrice;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Address;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Price;
use RoundlyConsulting\Shops\Orders\Enums\PriceType;
use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Shops\CurrentShop;
use RoundlyConsulting\Shops\Shops\Shop;
use RoundlyConsulting\Shops\Support\Casts\AddressCast;

/**
 * @property int $id
 * @property string $number
 * @property Status $status
 * @property int|null $coupon_id
 * @property int|null $shop_id
 * @property string|null $customer_type
 * @property int|string|null $customer_id
 * @property Currency $currency
 * @property PriceType $price_type
 * @property Money|null $store_credit_applied
 * @property Money|null $discount
 * @property bool $free_shipping
 * @property string|null $coupon_code
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
 * @property-read Model|null $customer
 */
final class Order extends Model
{
    use BelongsToShop;
    use HasCoupon;
    use HasCustomer;
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
     * Snapshot the currency on insert when none was set: the order's shop's (or the bound
     * current shop's), else the configured default. A placed order keeps it even when
     * SHOPS_DEFAULT_CURRENCY changes later. The catalog price type is snapshotted the same
     * way. Then assign the order number (unless set).
     * Done here rather than in a `creating` listener so both hold under `Event::fake()`
     * too — the columns are NOT NULL.
     *
     * @param  Builder<static>  $query
     */
    protected function performInsert(Builder $query): bool
    {
        if (($this->getAttributes()['currency'] ?? null) === null) {
            $this->currency = $this->resolveShopForCurrency()?->currency()
                ?? Currency::of((string) config('shops.pricing.default_currency', 'EUR'));
        }

        if (($this->getAttributes()['price_type'] ?? null) === null) {
            $this->price_type = PriceType::from((string) config('shops.pricing.price_type', 'gross'));
        }

        $this->assignNumber();

        return parent::performInsert($query);
    }

    private function resolveShopForCurrency(): ?Shop
    {
        $shop = $this->shop_id !== null ? $this->shop : app(CurrentShop::class)->get();

        return $shop instanceof Shop ? $shop : null;
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
            'currency' => AsCurrency::class,
            'price_type' => PriceType::class,
            'store_credit_applied' => AsMoney::currencyColumn('currency'),
            'discount' => AsMoney::currencyColumn('currency'),
            'free_shipping' => 'boolean',
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
