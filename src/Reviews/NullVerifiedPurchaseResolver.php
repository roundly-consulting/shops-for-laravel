<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Reviews;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Shops\Products\Product;
use RoundlyConsulting\Shops\Reviews\Contracts\VerifiedPurchaseResolver;

/**
 * A no-op resolver that never marks a review as a verified purchase. Bound by
 * default so review verification is opt-in: a host wires a real resolver (or the
 * bundled DatabaseVerifiedPurchaseResolver) when it wants the gate enforced.
 */
final class NullVerifiedPurchaseResolver implements VerifiedPurchaseResolver
{
    public function verified(Model $author, Product $product): bool
    {
        return false;
    }
}
