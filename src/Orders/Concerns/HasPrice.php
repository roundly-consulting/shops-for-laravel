<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Shops\Contracts\Coupon;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Price;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\PriceLine;
use RoundlyConsulting\Shops\Orders\Enums\PriceType;
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

            $lines = [];

            foreach ($items as $item) {
                $lines[] = new PriceLine(
                    unitPrice: $item->price,
                    quantity: $item->quantity,
                    taxClass: (string) ($item->getAttribute('tax_class') ?? 'standard'),
                );
            }

            $coupon = $this->coupon;
            $currency = (string) config('shops.pricing.default_currency', 'EUR');

            return new Price(
                lines: $lines,
                shipping: Money::zero($currency),
                priceType: PriceType::from((string) config('shops.pricing.price_type', 'gross')),
                taxResolver: null,
                coupon: $coupon instanceof Coupon ? $coupon : null,
                currency: $currency,
            );
        });
    }
}
