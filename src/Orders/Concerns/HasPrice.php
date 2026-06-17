<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Shops\Contracts\Coupon;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Price;
use RoundlyConsulting\Shops\Orders\Item;
use RoundlyConsulting\Shops\Support\Money\Money;

/**
 * @phpstan-require-extends Model
 */
trait HasPrice
{
    /**
     * @return Attribute<Price, never>
     */
    protected function price(): Attribute
    {
        return Attribute::get(function (): Price {
            /** @var iterable<Item> $items */
            $items = $this->items;

            $prices = [];

            foreach ($items as $item) {
                $prices[] = $item->price;
            }

            $price = $prices === []
                ? Money::EUR(0)
                : Money::sum(...$prices);

            $coupon = $this->coupon;

            return new Price(
                price: $price,
                shipping: Money::EUR(0),
                taxRate: (int) config('shops.tax_rate'),
                coupon: $coupon instanceof Coupon ? $coupon : null,
            );
        });
    }
}
