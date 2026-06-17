<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Tests\Fixtures\PlainSluggable;

it('generates a single-string slug from the name', function (): void {
    $model = PlainSluggable::create(['name' => 'Hello World']);

    expect($model->slug)->toBe('hello-world');
});

it('keeps an explicitly provided slug', function (): void {
    $model = PlainSluggable::create(['name' => 'Hello World', 'slug' => 'custom']);

    expect($model->slug)->toBe('custom');
});

it('uses the slug as the route key', function (): void {
    expect((new PlainSluggable)->getRouteKeyName())->toBe('slug');
});
