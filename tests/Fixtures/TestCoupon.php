<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Shops\Contracts\Coupon;
use RoundlyConsulting\Shops\Support\Money\Money;

/**
 * A minimal coupon model used only by the test suite to exercise the package's
 * Coupon contract. It applies a flat percentage discount.
 *
 * @property int $id
 * @property int $value
 * @property int $usage
 * @property int $max_usage
 */
final class TestCoupon extends Model implements Coupon
{
    /** @use HasFactory<TestCouponFactory> */
    use HasFactory;

    protected $table = 'coupons';

    protected $guarded = [];

    protected static function newFactory(): TestCouponFactory
    {
        return TestCouponFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'int',
            'usage' => 'int',
            'max_usage' => 'int',
        ];
    }

    public function canBeApplied(): bool
    {
        return $this->usage < $this->max_usage;
    }

    public function apply(Money $money): Money
    {
        $discount = $money->multiply($this->value)->divide(100);

        return $money->subtract($discount);
    }

    public function recordUsage(): void
    {
        $this->increment('usage');
    }
}
