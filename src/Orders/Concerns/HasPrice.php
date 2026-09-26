<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Contracts\TaxResolver;
use RoundlyConsulting\Shops\Orders\Actions\AddOrderItemAction;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Address;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Price;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\PriceLine;
use RoundlyConsulting\Shops\Orders\Enums\PriceType;
use RoundlyConsulting\Shops\Orders\Item;
use RoundlyConsulting\Shops\Shops\Shop;
use RoundlyConsulting\Shops\Support\Tax\TaxRateValue;

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
     * Likewise each item's snapshotted tax rate and the order's `price_type` are used as
     * they were at placement, never today's rates or config.
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
                $taxClass = (string) ($item->getAttribute('tax_class') ?? 'standard');
                $taxRate = $item->getAttribute('tax_rate');

                $lines[] = new PriceLine(
                    unitPrice: $item->price,
                    quantity: $item->quantity,
                    taxClass: $taxClass,
                    // The rate the line was taxed at when it was added — never re-resolved.
                    taxRate: is_int($taxRate)
                        ? new TaxRateValue($taxRate, $taxClass, label: $item->getAttribute('tax_label'))
                        : null,
                );
            }

            // The order's own snapshotted currency — never the (changeable) config.
            $currency = $this->getAttribute('currency');
            $currency = $currency instanceof Currency ? $currency : Currency::of((string) config('shops.pricing.default_currency', 'EUR'));
            $discount = $this->getAttribute('discount');
            $priceType = $this->getAttribute('price_type');

            return new Price(
                lines: $lines,
                shipping: Money::zero($currency),
                // The snapshotted price type; config only for an order not yet inserted.
                priceType: $priceType instanceof PriceType
                    ? $priceType
                    : PriceType::from((string) config('shops.pricing.price_type', 'gross')),
                taxResolver: null,
                discount: $discount instanceof Money ? $discount : null,
                freeShipping: (bool) $this->getAttribute('free_shipping'),
                currency: $currency,
                shop: $this->resolveShop(),
                country: $this->resolveCountry(),
            );
        });
    }

    /**
     * The rate a line of the given tax class is taxed at on this order right now — its shop's
     * rates, for its shipping destination. {@see AddOrderItemAction} snapshots it onto each
     * item, so a later rate edit or address change never re-prices the placed order.
     */
    public function taxRateFor(string $taxClass): TaxRateValue
    {
        return app(TaxResolver::class)->rateFor($this->resolveShop(), $taxClass, $this->resolveCountry());
    }

    /**
     * The share of the final price settled through the payment gateway: the final price
     * minus any store credit already applied, never below zero. It is what a gateway's
     * `charge()` takes, and the most a gateway refund can return.
     */
    public function gatewayAmount(): Money
    {
        /** @var Price $price */
        $price = $this->getAttribute('price');
        $final = $price->getFinalPrice();
        $credit = $this->getAttribute('store_credit_applied');

        if (! $credit instanceof Money) {
            return $final;
        }

        return Money::max([Money::zero($final->currency()), $final->subtract($credit)]);
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
