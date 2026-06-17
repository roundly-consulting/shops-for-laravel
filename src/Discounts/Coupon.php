<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Discounts;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Shops\Concerns\BelongsToShop;
use RoundlyConsulting\Shops\Contracts\Coupon as CouponContract;
use RoundlyConsulting\Shops\Database\Factories\CouponFactory;
use RoundlyConsulting\Shops\Discounts\DataTransferObjects\CouponConditions;
use RoundlyConsulting\Shops\Discounts\Enums\DiscountType;
use RoundlyConsulting\Shops\Exceptions\CurrencyMismatchException;
use RoundlyConsulting\Shops\Support\Money\Money;

/**
 * A shippable reference coupon implementing the package's Coupon contract:
 * percentage or fixed-amount discounts guarded by usage limit, active window,
 * and minimum spend. Hosts can use it as-is (point `shops.discounts.coupon_model`
 * at it) or swap in their own model.
 *
 * @property int $id
 * @property string $code
 * @property DiscountType $type
 * @property int $value
 * @property string|null $currency
 * @property int|null $max_usage
 * @property int $usage
 * @property int|null $minimum_spend
 * @property CarbonInterface|null $starts_at
 * @property CarbonInterface|null $expires_at
 */
final class Coupon extends Model implements CouponContract
{
    use BelongsToShop;
    /** @use HasFactory<CouponFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'coupons';

    protected $guarded = [];

    protected static function newFactory(): CouponFactory
    {
        return CouponFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'value' => 'int',
            'max_usage' => 'int',
            'usage' => 'int',
            'minimum_spend' => 'int',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function canBeApplied(?Money $spend = null): bool
    {
        return $this->conditions()->passes(now(), $spend);
    }

    public function apply(Money $money): Money
    {
        return match ($this->type) {
            DiscountType::Percentage => $money->subtract($money->multiply($this->value)->divide(100)),
            DiscountType::Fixed => $this->applyFixed($money),
        };
    }

    public function recordUsage(): void
    {
        $this->increment('usage');
    }

    public function conditions(): CouponConditions
    {
        return new CouponConditions(
            maxUsage: $this->max_usage,
            usage: $this->usage,
            minimumSpend: $this->minimum_spend !== null && $this->currency !== null
                ? Money::of($this->minimum_spend, $this->currency)
                : null,
            startsAt: $this->starts_at,
            expiresAt: $this->expires_at,
        );
    }

    private function applyFixed(Money $money): Money
    {
        $currency = $this->currency ?? $money->getCurrency()->getCode();

        if ($currency !== $money->getCurrency()->getCode()) {
            throw CurrencyMismatchException::between($money->getCurrency()->getCode(), $currency);
        }

        $discount = Money::of($this->value, $money->getCurrency()->getCode());

        // Never discount below zero.
        return $money->compareTo($discount) <= 0
            ? Money::zero($money->getCurrency()->getCode())
            : $money->subtract($discount);
    }
}
