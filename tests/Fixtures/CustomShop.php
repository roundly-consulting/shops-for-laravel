<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Tests\Fixtures;

use RoundlyConsulting\Shops\Shops\Shop;

/**
 * A host's own tenant model, extending the packaged one — exactly what
 * `shops.shop_model` invites and what `final` used to forbid.
 */
final class CustomShop extends Shop
{
    protected $table = 'shops';
}
