<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;
use RoundlyConsulting\Shops\Shops\Shop;

/**
 * Resolves the tenant model every shop-owned record hangs off, from
 * `shops.shop_model`.
 *
 * Deliberately **not** narrowed to {@see Shop}. Unlike the package's other
 * configurable models, a host may point this at a tenant model of its own
 * (a Team, an Account, a Site) that has no reason to extend ours — every owned
 * record only ever needs its primary key, through a plain `shop_id` column. So
 * the toolkit's is-a-Model validation is exactly this key's contract, and a
 * non-Shop model must be honoured rather than quietly replaced.
 *
 * A host that wants the package's own tenant behaviour (translations, tax rates,
 * `Shop::current()`, and the sluggable-backed slug + route key it inherits)
 * extends {@see Shop} instead.
 */
final class ShopModel
{
    /** @return class-string<Model> */
    public static function class(): string
    {
        return ModelResolver::for('shops.shop_model', Shop::class);
    }

    /** @return Builder<Model> */
    public static function query(): Builder
    {
        return self::class()::query();
    }
}
