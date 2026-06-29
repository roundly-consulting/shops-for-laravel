<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Reviews;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Shops\Orders\Item;
use RoundlyConsulting\Shops\Products\Product;
use RoundlyConsulting\Shops\Reviews\Contracts\VerifiedPurchaseResolver;

/**
 * Marks a review as a verified purchase when the author has an order that has
 * been both paid and fulfilled containing the reviewed product. Relies on the
 * optional Order.customer morph (see the customer migration); with no linked
 * customer no order can match and reviews stay unverified.
 */
final class DatabaseVerifiedPurchaseResolver implements VerifiedPurchaseResolver
{
    public function verified(Model $author, Product $product): bool
    {
        return Item::query()
            ->where('product_id', $product->getKey())
            ->whereHas('order', function (Builder $query) use ($author): void {
                $query->whereMorphedTo('customer', $author)
                    ->whereNotNull('paid_at')
                    ->whereNotNull('fulfilled_at');
            })
            ->exists();
    }
}
