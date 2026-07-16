<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Tests\Fixtures;

use RoundlyConsulting\Shops\Shops\Shop;
use RoundlyConsulting\Testing\Fixtures\Concerns\CountsCreations;

/**
 * A host's own tenant model, extending the packaged one — exactly what
 * `shops.shop_model` invites and what `final` used to forbid (#19).
 *
 * `CountsCreations` is what makes the swap proof real rather than cosmetic: a row
 * created through `static::query()` inside the packaged model still passes
 * `instanceof CustomShop`, but never fires the host's model events. Counting the
 * `created` events that actually land here is the only proof the row was created
 * *as* this class.
 */
final class CustomShop extends Shop
{
    use CountsCreations;

    protected $table = 'shops';
}
