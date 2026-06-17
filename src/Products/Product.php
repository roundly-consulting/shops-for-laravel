<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Products;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Shops\Concerns\BelongsToShop;
use RoundlyConsulting\Shops\Concerns\HasSlug;
use RoundlyConsulting\Shops\Database\Factories\ProductFactory;
use RoundlyConsulting\Shops\Products\Concerns\BelongsToManyCategories;
use RoundlyConsulting\Shops\Support\Casts\MoneyCast;
use RoundlyConsulting\Shops\Support\Money\Money;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property Money $price
 * @property string $currency
 * @property CarbonInterface|null $published_at
 */
final class Product extends Model
{
    use BelongsToManyCategories;
    use BelongsToShop;
    /** @use HasFactory<ProductFactory> */
    use HasFactory;
    use HasSlug;
    use SoftDeletes;

    protected $table = 'products';

    protected $guarded = [];

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'price' => MoneyCast::class,
        ];
    }
}
