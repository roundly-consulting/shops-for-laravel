<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Facades;

use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Shops\ShopsManager;
use RoundlyConsulting\Shops\Testing\ShopsFake;

/**
 * @method static \RoundlyConsulting\Shops\Handles\CartHandle cart(\RoundlyConsulting\Shops\Cart\Cart $cart)
 * @method static \RoundlyConsulting\Shops\Handles\OrderHandle order(\RoundlyConsulting\Shops\Orders\Order $order)
 * @method static \RoundlyConsulting\Shops\Handles\InventoryHandle inventory(\RoundlyConsulting\Shops\Products\ProductVariant $variant)
 * @method static \RoundlyConsulting\Shops\Handles\CouponsHandle coupons()
 * @method static \RoundlyConsulting\Shops\Shops\CurrentShop current()
 * @method static \RoundlyConsulting\Shops\Orders\AddressBook addresses()
 * @method static void assertOrderPlaced(\RoundlyConsulting\Shops\Cart\Cart|null $cart = null)
 * @method static void assertNothingPlaced()
 * @method static void assertCharged(\RoundlyConsulting\Shops\Orders\Order|null $order = null)
 * @method static void assertNothingCharged()
 * @method static void assertTransitioned(\RoundlyConsulting\Shops\Orders\Order|null $order = null, \RoundlyConsulting\Shops\Orders\Enums\Status|null $to = null)
 * @method static void assertNothingTransitioned()
 * @method static void assertStockAdjusted(\RoundlyConsulting\Shops\Products\ProductVariant|null $variant = null, int|null $delta = null, \RoundlyConsulting\Shops\Inventory\Enums\StockReason|null $reason = null)
 * @method static void assertNothingStockAdjusted()
 * @method static void assertCartChanged(\RoundlyConsulting\Shops\Cart\Cart|null $cart = null, \RoundlyConsulting\Shops\Testing\CartChange|null $change = null)
 * @method static void assertNothingCartChanged()
 *
 * @see ShopsManager
 * @see ShopsFake
 */
final class Shops extends Facade
{
    /**
     * Swap in the recording manager. Operations still run; every state change made through
     * the facade, an injected ShopsManager or a model convenience method is recorded for the
     * `assert*()` methods.
     */
    public static function fake(): ShopsFake
    {
        $fake = app(ShopsFake::class);

        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return ShopsManager::class;
    }
}
