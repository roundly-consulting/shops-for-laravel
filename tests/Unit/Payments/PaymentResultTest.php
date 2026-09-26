<?php

declare(strict_types=1);

use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Payments\PaymentResult;

it('builds a successful result', function (): void {
    $result = PaymentResult::success(Money::ofMinor(1000, 'EUR'), 'txn_123');

    expect($result->successful)->toBeTrue()
        ->and($result->amount->minor())->toBe('1000')
        ->and($result->reference)->toBe('txn_123')
        ->and($result->message)->toBeNull();
});

it('builds a failed result', function (): void {
    $result = PaymentResult::failure(Money::ofMinor(1000, 'EUR'), 'card declined');

    expect($result->successful)->toBeFalse()
        ->and($result->reference)->toBeNull()
        ->and($result->message)->toBe('card declined');
});
