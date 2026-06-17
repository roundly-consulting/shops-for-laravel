<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Facades;

use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Shops\ShopManager;

/**
 * @method static \RoundlyConsulting\Shops\Orders\Order placeOrder(\RoundlyConsulting\Shops\Cart\Cart $cart, \RoundlyConsulting\Shops\Orders\DataTransferObjects\PlaceOrderData $data)
 * @method static \RoundlyConsulting\Shops\Orders\Order transition(\RoundlyConsulting\Shops\Orders\Order $order, \RoundlyConsulting\Shops\Orders\Enums\Status $to)
 * @method static \RoundlyConsulting\Shops\Payments\PaymentResult charge(\RoundlyConsulting\Shops\Orders\Order $order)
 * @method static \RoundlyConsulting\Shops\Support\Money\Money useCoupon(\RoundlyConsulting\Shops\Contracts\Coupon $coupon, \RoundlyConsulting\Shops\Support\Money\Money $money)
 *
 * @see ShopManager
 */
final class Shop extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ShopManager::class;
    }
}
