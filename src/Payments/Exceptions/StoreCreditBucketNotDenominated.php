<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Payments\Exceptions;

use RoundlyConsulting\Shops\Exceptions\ShopsException;

/**
 * The store-credit bucket is not mapped to a currency in `credits.currencies`, so its
 * integers are plain credits — there is no honest way to pay an order with them.
 */
final class StoreCreditBucketNotDenominated extends ShopsException
{
    public static function forBucket(string $bucket): self
    {
        return new self("The store-credit bucket [{$bucket}] is not denominated in a currency: map it in credits.currencies (e.g. '{$bucket}' => 'EUR').");
    }
}
