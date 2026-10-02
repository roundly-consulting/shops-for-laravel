<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Payments\Exceptions;

use RoundlyConsulting\Shops\Actions\Orders\ChargeOrderAction;
use RoundlyConsulting\Shops\Payments\PaymentResult;
use RuntimeException;

/**
 * Carries a declined gateway result out of {@see ChargeOrderAction}'s transaction, so the
 * transaction rolls back (returning any store credit the attempt applied). It never escapes
 * the action: the caller gets the failed PaymentResult.
 *
 * @internal
 */
final class DeclinedCharge extends RuntimeException
{
    public function __construct(
        public readonly PaymentResult $result,
    ) {
        parent::__construct('The payment gateway declined the charge.');
    }
}
