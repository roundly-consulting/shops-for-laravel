<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Handles;

use Illuminate\Contracts\Container\Container;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Contracts\DiscountResolver;
use RoundlyConsulting\Shops\Discounts\DiscountResult;

/**
 * Coupons as the shop sees them, returned by `Shops::coupons()`. Redemption happens at
 * checkout; this side only previews.
 */
final readonly class CouponsHandle
{
    public function __construct(
        private Container $container,
    ) {}

    /**
     * The discount a code would take off a goods subtotal — and whether it grants free
     * shipping — through the bound DiscountResolver. Records no redemption. An unknown or
     * currently non-redeemable code previews as a zero discount.
     */
    public function preview(string $code, Money $goods): DiscountResult
    {
        return $this->container->make(DiscountResolver::class)->resolve($code, $goods);
    }
}
