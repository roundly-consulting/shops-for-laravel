<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Payments\PaymentResult;
use RoundlyConsulting\Shops\Support\Money\Money;

it('builds a successful result', function (): void {
    $result = PaymentResult::success(Money::EUR(1000), 'txn_123');

    expect($result->successful)->toBeTrue()
        ->and($result->amount->getAmount())->toBe('1000')
        ->and($result->reference)->toBe('txn_123')
        ->and($result->message)->toBeNull();
});

it('builds a failed result', function (): void {
    $result = PaymentResult::failure(Money::EUR(1000), 'card declined');

    expect($result->successful)->toBeFalse()
        ->and($result->reference)->toBeNull()
        ->and($result->message)->toBe('card declined');
});
