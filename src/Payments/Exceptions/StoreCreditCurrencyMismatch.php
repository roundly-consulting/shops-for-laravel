<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Payments\Exceptions;

use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Shops\Exceptions\ShopsException;

/**
 * The store-credit bucket is denominated in another currency than the order.
 */
final class StoreCreditCurrencyMismatch extends ShopsException
{
    public static function between(string $bucket, Currency $bucketCurrency, Currency $orderCurrency): self
    {
        return new self("The store-credit bucket [{$bucket}] holds {$bucketCurrency->code} and cannot pay an order in {$orderCurrency->code}.");
    }
}
