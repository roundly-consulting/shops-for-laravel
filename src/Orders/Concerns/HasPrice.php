<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Contracts\DiscountResolver;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Address;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Price;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\PriceLine;
use RoundlyConsulting\Shops\Orders\Enums\PriceType;
use RoundlyConsulting\Shops\Orders\Item;
use RoundlyConsulting\Shops\Shops\Shop;

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

            // The order's own snapshotted currency — never the (changeable) config.
            $currency = $this->getAttribute('currency');
            $currency = $currency instanceof Currency ? $currency : Currency::of((string) config('shops.pricing.default_currency', 'EUR'));
            $priceType = PriceType::from((string) config('shops.pricing.price_type', 'gross'));

            $base = new Price(
                lines: $lines,
                shipping: Money::zero($currency),
                priceType: $priceType,
                taxResolver: null,
                currency: $currency,
                shop: $this->resolveShop(),
                country: $this->resolveCountry(),
            );

            $code = $this->couponCode();

            if ($code === null) {
                return $base;
            }

            $result = app(DiscountResolver::class)->resolve($code, $base->getSubtotal());

            return new Price(
                lines: $lines,
                shipping: Money::zero($currency),
                priceType: $priceType,
                taxResolver: null,
                discount: $result->discount,
                freeShipping: $result->freeShipping,
                currency: $currency,
                shop: $this->resolveShop(),
                country: $this->resolveCountry(),
            );
        });
    }

    private function couponCode(): ?string
    {
        $coupon = $this->getAttribute('coupon');

        if (! $coupon instanceof Model) {
            return null;
        }

        $code = $coupon->getAttribute('code');

        return is_string($code) && $code !== '' ? $code : null;
    }

    private function resolveShop(): ?Shop
    {
        if ($this->getAttribute('shop_id') === null) {
            return null;
        }

        $shop = $this->getAttribute('shop');

        return $shop instanceof Shop ? $shop : null;
    }

    private function resolveCountry(): ?string
    {
        $address = $this->getAttribute('shipping_address');

        if ($address instanceof Address && $address->countryIso !== '') {
            return $address->countryIso;
        }

        return null;
    }
}
