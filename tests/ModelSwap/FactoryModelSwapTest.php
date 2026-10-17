<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Shops\Shop;
use RoundlyConsulting\Shops\Tests\Fixtures\CustomShop;

/*
 * The factory names the packaged Shop in `$model`, so without a `modelName()` override
 * returning the seam it keeps building the packaged class after a host swapped the tenant —
 * and the host's events, observers and casts never run on factory-made shops.
 */
it('builds the configured tenant model from the factory', function (): void {
    expect(Shop::factory()->create()::class)->toBe(CustomShop::class);
});
