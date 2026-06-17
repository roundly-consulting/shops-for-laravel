<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Products\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use RoundlyConsulting\Shops\Products\ProductVariant;
use RoundlyConsulting\Shops\Support\Money\Money;

/**
 * Gives a product one-to-many variants (its sellable units), an implicit
 * default variant for simple single-SKU products, and a read-only `price`
 * proxy to that default variant for back-compat reads.
 *
 * @phpstan-require-extends Model
 *
 * @property-read Collection<int, ProductVariant> $variants
 * @property-read ProductVariant|null $defaultVariant
 */
trait HasVariants
{
    public static function bootHasVariants(): void
    {
        static::created(function (Model $model): void {
            if ($model instanceof self) {
                $model->ensureDefaultVariant();
            }
        });
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    /**
     * The lowest-position variant, used as the product's default sellable unit.
     *
     * @return HasOne<ProductVariant, $this>
     */
    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class, 'product_id')
            ->orderBy('position')
            ->orderBy('id');
    }

    /**
     * Resolve the variant matching exactly the given set of option value ids.
     *
     * @param  list<int>  $optionValueIds
     */
    public function variantFor(array $optionValueIds): ?ProductVariant
    {
        $wanted = array_values(array_unique($optionValueIds));
        sort($wanted);

        return $this->variants()
            ->with('optionValues')
            ->get()
            ->first(function (ProductVariant $variant) use ($wanted): bool {
                $ids = $variant->optionValues->pluck('id')->map(intval(...))->all();
                sort($ids);

                return $ids === $wanted;
            });
    }

    /**
     * Read-only proxy to the default variant's price so simple products keep a
     * single `price` read. Falls back to a zero amount in the configured
     * currency when the product has no variant yet (e.g. before it is saved).
     *
     * @return Attribute<Money, never>
     */
    protected function price(): Attribute
    {
        return Attribute::get(function (): Money {
            $variant = $this->variants()->orderBy('position')->orderBy('id')->first();

            if ($variant === null) {
                return Money::zero((string) config('shops.pricing.default_currency', 'EUR'));
            }

            return $variant->price;
        });
    }

    /**
     * Create a default variant when a product is created without any explicit
     * variant, so single-SKU products stay a one-liner.
     */
    protected function ensureDefaultVariant(): void
    {
        if ($this->variants()->exists()) {
            return;
        }

        $currency = (string) config('shops.pricing.default_currency', 'EUR');

        $this->variants()->create([
            'sku' => $this->defaultVariantSku(),
            'name' => null,
            'price' => Money::zero($currency),
            'currency' => $currency,
        ]);
    }

    protected function defaultVariantSku(): string
    {
        return mb_strtoupper(Str::slug((string) $this->getAttribute('slug')).'-DEFAULT');
    }
}
