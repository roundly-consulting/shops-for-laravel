<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Shops;

use Closure;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Shops\Concerns\BelongsToShop;

/**
 * Holds the shop that owns records created in the current container context.
 *
 * Bound as a singleton so a host can set the active tenant once (per request,
 * job, or scoped block) and have {@see BelongsToShop}
 * auto-fill `shop_id` on new owned records. An explicitly set `shop_id` always
 * wins; with nothing bound, nothing is auto-filled.
 */
final class CurrentShop
{
    private Model|int|null $shop = null;

    public function set(Model|int|null $shop): void
    {
        $this->shop = $shop;
    }

    public function get(): ?Model
    {
        if ($this->shop instanceof Model) {
            return $this->shop;
        }

        if ($this->shop === null) {
            return null;
        }

        /** @var class-string<Model> $class */
        $class = Shop::resolveModelClass();

        /** @var Model|null $model */
        $model = $class::query()->find($this->shop);

        $this->shop = $model;

        return $model;
    }

    public function id(): ?int
    {
        if ($this->shop instanceof Model) {
            /** @var int|string|null $key */
            $key = $this->shop->getKey();

            return $key === null ? null : (int) $key;
        }

        return $this->shop;
    }

    public function forget(): void
    {
        $this->shop = null;
    }

    /**
     * Run the callback with the given shop bound as current, restoring the
     * previous binding afterwards (even if the callback throws).
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(Model|int $shop, Closure $callback): mixed
    {
        $previous = $this->shop;

        $this->shop = $shop;

        try {
            return $callback();
        } finally {
            $this->shop = $previous;
        }
    }
}
