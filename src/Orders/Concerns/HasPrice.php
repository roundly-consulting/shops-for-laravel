<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Money\Money;
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
     * The order's price from its items and the discount snapshotted when it was placed
     * (`discount` + `free_shipping`). The coupon is never re-resolved here: its current
     * redeemability — expired, revoked, used up by this very order — must not re-price a
     * placed order, since the charge, store credit and refund credit-back all read this.
     *
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
            $discount = $this->getAttribute('discount');

            return new Price(
                lines: $lines,
                shipping: Money::zero($currency),
                priceType: PriceType::from((string) config('shops.pricing.price_type', 'gross')),
                taxResolver: null,
                discount: $discount instanceof Money ? $discount : null,
                freeShipping: (bool) $this->getAttribute('free_shipping'),
                currency: $currency,
                shop: $this->resolveShop(),
                country: $this->resolveCountry(),
            );
        });
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
