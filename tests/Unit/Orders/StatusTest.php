<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Orders\Enums\Status;

it('checks a single status', function (): void {
    expect(Status::Completed->is(Status::Completed))->toBeTrue()
        ->and(Status::Completed->is(Status::New))->toBeFalse();
});

it('checks membership in a set of statuses', function (): void {
    expect(Status::InProgress->isIn([Status::InProgress, Status::Completed]))->toBeTrue()
        ->and(Status::New->isIn([Status::InProgress, Status::Completed]))->toBeFalse();
});
