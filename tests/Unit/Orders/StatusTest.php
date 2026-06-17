<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Orders\Enums\Status;

it('checks a single status', function (): void {
    expect(Status::Fulfilled->is(Status::Fulfilled))->toBeTrue()
        ->and(Status::Fulfilled->is(Status::New))->toBeFalse();
});

it('checks membership in a set of statuses', function (): void {
    expect(Status::InProgress->isIn([Status::InProgress, Status::Fulfilled]))->toBeTrue()
        ->and(Status::New->isIn([Status::InProgress, Status::Fulfilled]))->toBeFalse();
});

it('exposes the allowed transitions for each status', function (Status $from, array $expected): void {
    expect($from->allowedTransitions())->toBe($expected);
})->with([
    'new' => [Status::New, [Status::InProgress, Status::Canceled]],
    'in progress' => [Status::InProgress, [Status::Paid, Status::Canceled]],
    'paid' => [Status::Paid, [Status::Fulfilled, Status::Refunded]],
    'fulfilled' => [Status::Fulfilled, [Status::Refunded]],
    'canceled' => [Status::Canceled, []],
    'refunded' => [Status::Refunded, []],
]);

it('allows valid transitions', function (Status $from, Status $to): void {
    expect($from->canTransitionTo($to))->toBeTrue();
})->with([
    [Status::New, Status::InProgress],
    [Status::New, Status::Canceled],
    [Status::InProgress, Status::Paid],
    [Status::InProgress, Status::Canceled],
    [Status::Paid, Status::Fulfilled],
    [Status::Paid, Status::Refunded],
    [Status::Fulfilled, Status::Refunded],
]);

it('forbids invalid transitions', function (Status $from, Status $to): void {
    expect($from->canTransitionTo($to))->toBeFalse();
})->with([
    [Status::New, Status::Paid],
    [Status::New, Status::Fulfilled],
    [Status::New, Status::Refunded],
    [Status::InProgress, Status::Fulfilled],
    [Status::InProgress, Status::Refunded],
    [Status::Paid, Status::New],
    [Status::Paid, Status::Canceled],
    [Status::Fulfilled, Status::Paid],
    [Status::Canceled, Status::New],
    [Status::Canceled, Status::InProgress],
    [Status::Refunded, Status::Paid],
]);

it('identifies terminal statuses', function (Status $status, bool $terminal): void {
    expect($status->isTerminal())->toBe($terminal);
})->with([
    [Status::New, false],
    [Status::InProgress, false],
    [Status::Paid, false],
    [Status::Fulfilled, false],
    [Status::Canceled, true],
    [Status::Refunded, true],
]);

it('maps each status to its timestamp column', function (Status $status, ?string $column): void {
    expect($status->timestampColumn())->toBe($column);
})->with([
    [Status::New, null],
    [Status::InProgress, 'in_progress_at'],
    [Status::Paid, 'paid_at'],
    [Status::Fulfilled, 'fulfilled_at'],
    [Status::Canceled, 'canceled_at'],
    [Status::Refunded, 'refunded_at'],
]);
