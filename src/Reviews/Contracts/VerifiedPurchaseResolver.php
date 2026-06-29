<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Reviews\Contracts;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Shops\Products\Product;

/**
 * Decides whether a review author has actually purchased the product they are
 * reviewing, so the review can be stamped as a verified purchase. The bound
 * implementation is configured via `shops.reviews.verified_purchase_resolver`.
 */
interface VerifiedPurchaseResolver
{
    public function verified(Model $author, Product $product): bool;
}
