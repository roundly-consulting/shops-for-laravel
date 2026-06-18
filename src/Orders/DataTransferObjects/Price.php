<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\DataTransferObjects;

use RoundlyConsulting\Shops\Contracts\Coupon;
use RoundlyConsulting\Shops\Contracts\TaxResolver;
use RoundlyConsulting\Shops\Orders\Enums\PriceType;
use RoundlyConsulting\Shops\Shops\Shop;
use RoundlyConsulting\Shops\Support\Money\Money;

/**
 * Quantity-aware, precision-correct price calculation for a set of lines.
 *
 * Each {@see PriceLine} is taxed per line at its tax class's rate. For the
 * `gross` price type the tax is *extracted* from the (tax-inclusive) line
 * price; for the `net` price type the tax is *added on top*. Lines are summed,
 * then any coupon discount and shipping are applied. All arithmetic stays in
 * minor units and rounds half-up at the line level.
 */
final readonly class Price
{
    private TaxResolver $taxResolver;

    /**
     * @param  list<PriceLine>  $lines
     */
    public function __construct(
        public array $lines,
        public Money $shipping,
        public PriceType $priceType = PriceType::Gross,
        ?TaxResolver $taxResolver = null,
        public ?Coupon $coupon = null,
        private string $currency = 'EUR',
        private ?Shop $shop = null,
        private ?string $country = null,
    ) {
        $this->taxResolver = $taxResolver ?? app(TaxResolver::class);
    }

    /**
     * The catalog subtotal (sum of line totals) before discount, in the price
     * type's meaning (gross for gross catalogs, net for net catalogs).
     */
    public function getSubtotal(): Money
    {
        return $this->sumLines();
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
     * Total tax across all goods, after discount. Shipping is treated as
     * untaxed by this default engine (a host can override via the resolver).
     */
    public function getTaxPrice(): Money
    {
        $catalog = $this->sumLines();
        $tax = $this->sumLineTax();

        if ($catalog->isZero()) {
            return $tax;
        }

        // Scale the catalog tax by the discount ratio so a coupon reduces the
        // tax proportionally to the value it removed.
        $ratio = $this->getPriceAfterDiscount()->getMinorAmount() / $catalog->getMinorAmount();

        return $tax->multiply($ratio);
    }

    /**
     * The goods value after any coupon discount, in the price type's meaning,
     * excluding shipping.
     */
    public function getPriceAfterDiscount(): Money
    {
        $subtotal = $this->sumLines();

        return $this->coupon?->apply($subtotal) ?? $subtotal;
    }

    /**
     * The discount amount removed by the coupon, if any.
     */
    public function getDiscountValue(): Money
    {
        return $this->sumLines()->subtract($this->getPriceAfterDiscount());
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

        return $goods->add($this->shipping);
    }

    private function sumLines(): Money
    {
        if ($this->lines === []) {
            return Money::zero($this->currency);
        }

        $total = Money::zero($this->lines[0]->unitPrice->getCurrency()->getCode());

        foreach ($this->lines as $line) {
            $total = $total->add($line->lineTotal());
        }

        return $total;
    }

    private function sumLineTax(): Money
    {
        if ($this->lines === []) {
            return Money::zero($this->currency);
        }

        $total = Money::zero($this->lines[0]->unitPrice->getCurrency()->getCode());

        foreach ($this->lines as $line) {
            $total = $total->add($this->taxForLine($line));
        }

        return $total;
    }

    private function taxForLine(PriceLine $line): Money
    {
        $rate = $this->taxResolver->rateFor($this->shop, $line->taxClass, $this->country);
        $lineTotal = $line->lineTotal();

        if ($rate->isZero()) {
            return Money::zero($lineTotal->getCurrency()->getCode());
        }

        if ($this->priceType === PriceType::Gross) {
            // Extract the tax already baked into a gross price:
            // net = gross / (1 + rate); tax = gross - net.
            $net = $lineTotal->divide($rate->grossDivisor());

            return $lineTotal->subtract($net);
        }

        // Net price: add the tax on top.
        return $lineTotal->multiply($rate->basisPoints)->divide(10000);
    }
}
