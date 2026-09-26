<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\DataTransferObjects;

use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Money\Discounts\DiscountAllocator;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Money\Tax\TaxBreakdown;
use RoundlyConsulting\Money\Tax\TaxSummary;
use RoundlyConsulting\Shops\Contracts\TaxResolver;
use RoundlyConsulting\Shops\Orders\Enums\PriceType;
use RoundlyConsulting\Shops\Shops\Shop;

/**
 * Quantity-aware, exact price calculation for a set of lines, on money-for-laravel.
 *
 * The resolved discount (see DiscountResolver, backed by coupons-for-laravel) is capped at
 * the subtotal and spread over the lines in proportion to their totals with money's
 * {@see DiscountAllocator} (the shares sum to the discount exactly). Each line is then taxed
 * once, on its discounted amount, at its tax class's rate: for the `gross` price type the
 * tax is *extracted* from the tax-inclusive amount (`gross × 100 / (100 + rate)`, exact),
 * for `net` it is *added on top*. One rounding per line, half away from zero; no floats.
 * Free-shipping coupons zero the shipping line.
 */
final readonly class Price
{
    private TaxResolver $taxResolver;

    private Currency $currency;

    /**
     * @param  list<PriceLine>  $lines
     */
    public function __construct(
        public array $lines,
        public Money $shipping,
        public PriceType $priceType = PriceType::Gross,
        ?TaxResolver $taxResolver = null,
        public ?Money $discount = null,
        public bool $freeShipping = false,
        Currency|string $currency = 'EUR',
        private ?Shop $shop = null,
        private ?string $country = null,
    ) {
        $this->taxResolver = $taxResolver ?? app(TaxResolver::class);
        $this->currency = $currency instanceof Currency ? $currency : Currency::of($currency);
    }

    /**
     * The catalog subtotal (sum of line totals) before discount, in the price
     * type's meaning (gross for gross catalogs, net for net catalogs).
     */
    public function getSubtotal(): Money
    {
        return Money::sum($this->lineTotals(), currencyIfEmpty: $this->currency);
    }

    /**
     * Net (tax-exclusive) value of the goods after discount, excluding
     * shipping.
     */
    public function getNetPrice(): Money
    {
        if ($this->priceType === PriceType::Gross) {
            return $this->getPriceAfterDiscount()->subtract($this->getTaxPrice());
        }

        return $this->getPriceAfterDiscount();
    }

    /**
     * Total tax across all goods, after discount: the sum of each line's tax on its
     * discounted amount. Shipping is treated as untaxed by this default engine.
     */
    public function getTaxPrice(): Money
    {
        return Money::sum(
            array_map(static fn (TaxBreakdown $line): Money => $line->tax, $this->breakdowns()),
            currencyIfEmpty: $this->currency,
        );
    }

    /**
     * Net, tax and gross of the discounted goods grouped by rate — the VAT summary an
     * invoice prints. Built from the same per-line breakdowns as {@see self::getTaxPrice()},
     * so the two always agree.
     */
    public function taxSummary(): TaxSummary
    {
        return TaxSummary::of(...$this->breakdowns());
    }

    /**
     * The goods value after any resolved discount, in the price type's meaning,
     * excluding shipping. The discount is capped at the subtotal so goods never
     * drop below zero.
     */
    public function getPriceAfterDiscount(): Money
    {
        $subtotal = $this->getSubtotal();

        if ($this->discount === null || ! $this->discount->isPositive()) {
            return $subtotal;
        }

        return $subtotal->subtract(Money::min([$this->discount, $subtotal]));
    }

    /**
     * The discount amount removed by the coupon, if any.
     */
    public function getDiscountValue(): Money
    {
        return $this->getSubtotal()->subtract($this->getPriceAfterDiscount());
    }

    /**
     * The amount the customer pays: discounted goods plus shipping. For net
     * catalogs the tax is added on top; for gross catalogs it is already
     * included in the goods value.
     */
    public function getFinalPrice(): Money
    {
        $goods = $this->getPriceAfterDiscount();

        if ($this->priceType === PriceType::Net) {
            $goods = $goods->add($this->getTaxPrice());
        }

        return $goods->add($this->shippingCost());
    }

    /**
     * The shipping charge actually applied: zero when a free-shipping coupon is
     * in effect, otherwise the quoted shipping.
     */
    public function shippingCost(): Money
    {
        if ($this->freeShipping) {
            return Money::zero($this->shipping->currency());
        }

        return $this->shipping;
    }

    /**
     * @return list<Money>
     */
    private function lineTotals(): array
    {
        return array_map(static fn (PriceLine $line): Money => $line->lineTotal(), $this->lines);
    }

    /**
     * One tax breakdown per line, on the line total minus its share of the discount.
     *
     * @return list<TaxBreakdown>
     */
    private function breakdowns(): array
    {
        if ($this->lines === []) {
            return [];
        }

        $totals = $this->lineTotals();
        $shares = app(DiscountAllocator::class)->allocate($this->getDiscountValue(), ...$totals);
        $breakdowns = [];

        foreach ($this->lines as $index => $line) {
            $taxable = $totals[$index]->subtract($shares[$index]);
            $rate = $this->taxResolver->rateFor($this->shop, $line->taxClass, $this->country)->toTaxRate();

            $breakdowns[] = $this->priceType === PriceType::Gross
                ? $rate->breakdownFromGross($taxable)
                : $rate->breakdownFromNet($taxable);
        }

        return $breakdowns;
    }
}
