<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Tests\Fixtures;

use RoundlyConsulting\Shops\Tests\TestCase;

/**
 * The suite's base case with `shops.shop_model` already pointed at {@see CustomShop}
 * BEFORE the providers boot.
 *
 * Boot order is the whole point: the providers hang observers and relationship wiring
 * on whatever `shops.shop_model` names at boot. A `config()->set()` inside the test
 * body reads back correctly but leaves every listener on the packaged Shop — which is
 * precisely the shape that let media #28 ship.
 *
 * Note the `array_merge(parent::configBeforeBoot(), …)`: dropping it would silently
 * discard the base case's media/coupons/attributes wiring, the same decapitation an
 * un-parented `defineEnvironment()` override causes one level up.
 */
abstract class SwappedShopTestCase extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'shops.shop_model' => CustomShop::class,
        ]);
    }
}
