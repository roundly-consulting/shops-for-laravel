<?php

declare(strict_types=1);

arch('it does not use debugging functions')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('it declares strict types')
    ->expect('RoundlyConsulting\Shops')
    ->toUseStrictTypes();

arch('it does not depend on acme')
    ->expect('RoundlyConsulting\Shops')
    ->not->toUse('Acme');

it('exposes a single public execute method on every action', function (): void {
    $actions = collect(glob(__DIR__.'/../src/**/Actions/*.php') ?: [])
        ->merge(glob(__DIR__.'/../src/*/Actions/*.php') ?: [])
        ->map(fn (string $path): string => basename($path, '.php'))
        ->unique();

    expect($actions)->not->toBeEmpty();

    foreach (collect(glob(__DIR__.'/../src/*/Actions/*.php') ?: []) as $path) {
        $contents = (string) file_get_contents($path);

        if (! preg_match('/namespace (.+);/', $contents, $ns)) {
            continue;
        }

        $class = $ns[1].'\\'.basename($path, '.php');

        if (! class_exists($class)) {
            continue;
        }

        $publicMethods = array_filter(
            (new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC),
            fn (ReflectionMethod $m): bool => $m->class === $class && $m->getName() !== '__construct',
        );

        $names = array_values(array_map(fn (ReflectionMethod $m): string => $m->getName(), $publicMethods));

        expect($names)->toBe(['execute'], "{$class} should expose only execute()");
    }
});

it('only requires whitelisted runtime dependencies', function (): void {
    /** @var array<string, string> $require */
    $require = json_decode((string) file_get_contents(__DIR__.'/../composer.json'), true)['require'] ?? [];

    // The CI matrix injects testing tooling (orchestra/testbench, pest, …) into
    // "require" via `composer require`; the policy only forbids third-party
    // *runtime* dependencies, so allow whitelisted vendors plus that tooling.
    $allowed = '#^(php$|ext-|illuminate/|laravel/|symfony/|orchestra/|pestphp/|nunomaduro/|larastan/|phpstan/)#';

    $disallowed = array_values(array_filter(
        array_keys($require),
        fn (string $package): bool => preg_match($allowed, $package) !== 1,
    ));

    expect($disallowed)->toBe([]);
});
