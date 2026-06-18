<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Shops;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RoundlyConsulting\Shops\Database\Factories\TaxRateFactory;
use RoundlyConsulting\Shops\Support\Tax\DatabaseTaxResolver;
use RoundlyConsulting\Shops\Support\Tax\TaxRateValue;

/**
 * A single tax rate owned by a {@see Shop}. A shop may carry many rates; each
 * names a tax class, an optional ISO-3166-1 alpha-2 country, and a rate in basis
 * points (1900 = 19.00%). {@see DatabaseTaxResolver}
 * picks the applicable one for a (shop, class, country) lookup.
 *
 * @property int $id
 * @property int|null $shop_id
 * @property string $name
 * @property string $tax_class
 * @property string|null $country
 * @property int $rate
 * @property bool $is_default
 * @property int $priority
 */
final class TaxRate extends Model
{
    /** @use HasFactory<TaxRateFactory> */
    use HasFactory;

    protected $table = 'tax_rates';

    protected $guarded = [];

    protected static function newFactory(): TaxRateFactory
    {
        return TaxRateFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'integer',
            'is_default' => 'boolean',
            'priority' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::resolveModelClass(), 'shop_id');
    }

    public function toValue(): TaxRateValue
    {
        return new TaxRateValue(
            basisPoints: $this->rate,
            taxClass: $this->tax_class,
            country: $this->country,
            label: $this->name,
        );
    }
}
