<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Shops\Exceptions\ShopsException;
use RoundlyConsulting\Shops\Orders\NumberGenerators\DefaultNumberGenerator;
use RoundlyConsulting\Shops\Shops\Shop;
use RoundlyConsulting\Testing\Arch\ArchPresets;

// ── Shared presets ───────────────────────────────────────────────────────────

ArchPresets::strictTypes('RoundlyConsulting\Shops');

// Three intentional extension points are exempt: Shop, which `shops.shop_model`
// invites a host to subclass (pinned by the preset below instead); ShopsException,
// the base every shop error extends so a host can catch them uniformly; and
// DefaultNumberGenerator, which `shops.orders.number_generator` invites a host to
// extend rather than reimplement the whole NumberGenerator contract.
ArchPresets::finalByDefault('RoundlyConsulting\Shops')
    ->ignoring([Shop::class, ShopsException::class, DefaultNumberGenerator::class]);

// The counter-weight, and shops' own bug #19: `final class Shop` was a PHP fatal
// the moment a host used the documented `shops.shop_model` seam. Coupon is the
// coupons package's model, swapped through shops' own key — the same fatal is one
// `final` away in a package this one only consumes.
ArchPresets::swappableModelsAreNotFinal([
    Shop::class => 'shops.shop_model',
    Coupon::class => 'shops.discounts.coupon_model',
]);

// Shops does no cryptography of its own; the ban is a standing guard against an
// order-number or token scheme being hand-rolled here instead of in crypto.
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\Shops');

// The two swappable models both resolve through the Support seam (ShopModel,
// CouponModel). This is shops' bug #3 as a test: a hard-coded call site sitting
// beside an honoured config is exactly what a stray literal outside the seam is.
ArchPresets::modelsResolveThroughSeam(__DIR__.'/../src', 'Support');

// The morph-key seam, guarded. Shops' `owner` (carts), `customer` (orders) and
// `reference` (stock_adjustments) columns migrated off raw `$table->morphs()` onto
// `morphKey($name, KeyType::fromConfig(...))` so a uuid/ulid host can flip its whole graph
// coherently — a hardcoded bigint id breaks those hosts on Postgres, and SQLite type
// affinity hides it. This pin reds if any of the 14 migrations reintroduces a raw morph.
ArchPresets::morphColumnsUseTheSeam(__DIR__.'/../database/migrations');

// The Dependency Policy as a test. No `alsoAllow`: shops' `require` ships only
// php/illuminate/roundly, and the CI workflow installs test tooling with --dev, so
// nothing legitimately lands in `require` that this must forgive.
ArchPresets::runtimeRequireIsWhitelisted(__DIR__.'/../composer.json');

ArchPresets::noDebuggingLeftovers();

// ── shops-specific rules the presets don't express ────────────────────────────

arch('src only uses allowed vendor roots')
    ->expect('RoundlyConsulting\Shops')
    ->toOnlyUse([
        'RoundlyConsulting\Shops',
        'RoundlyConsulting\Shops\Database\Factories',
        'RoundlyConsulting\Addresses',
        'RoundlyConsulting\Attributes',
        'RoundlyConsulting\Coupons',
        'RoundlyConsulting\Credits',
        'RoundlyConsulting\Enums',
        'RoundlyConsulting\MediaLibrary',
        'RoundlyConsulting\PackageToolkit',
        'RoundlyConsulting\Reviews',
        'RoundlyConsulting\Sluggable',
        'Illuminate',
        'Carbon',
        'Closure',
        'RuntimeException',
        // native helpers used unqualified
        'app',
        'class_basename',
        'config',
        'config_path',
        'database_path',
        'now',
        'event',
        'filled',
        'resolve',
        'blank',
        '__',
    ]);

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
