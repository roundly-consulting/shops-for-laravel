<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Shops\Exceptions\ShopsException;
use RoundlyConsulting\Shops\Orders\NumberGenerators\DefaultNumberGenerator;
use RoundlyConsulting\Shops\Shops\Shop;
use RoundlyConsulting\Shops\ShopsManager;
use RoundlyConsulting\Testing\Arch\ArchPresets;

// ── Shared presets ───────────────────────────────────────────────────────────

ArchPresets::strictTypes('RoundlyConsulting\Shops');

// Four intentional extension points are exempt: Shop, which `shops.shop_model`
// invites a host to subclass (pinned by the preset below instead); ShopsException,
// the base every shop error extends so a host can catch them uniformly;
// DefaultNumberGenerator, which `shops.orders.number_generator` invites a host to
// extend rather than reimplement the whole NumberGenerator contract; and ShopsManager,
// the facade root `ShopsFake` extends — a fake that is not a subtype of the manager
// would TypeError every constructor-injected ShopsManager under `Shops::fake()`.
ArchPresets::finalByDefault('RoundlyConsulting\Shops')
    ->ignoring([Shop::class, ShopsException::class, DefaultNumberGenerator::class, ShopsManager::class]);

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

// One path into behaviour: model convenience methods and model traits reach actions through
// ShopsManager, so `Shops::fake()` sees `$cart->add()` and `$order->markPaid()`. Shops groups
// its models by area (`Cart\Cart`, `Orders\Order`, `Products\ProductVariant`, `Shops\Shop`,
// `Inventory\StockAdjustment`) with their traits beside them (`Orders\Concerns\HasNumber`); the
// preset finds every Eloquent model under the namespace and every package trait they use, so
// no per-folder rule is needed. The rest of those folders (DTOs, enums, events, exceptions and
// the `current()` / `addresses()` sub-accessors, which may resolve actions) is not model code.
ArchPresets::modelsGoThroughTheFacade('RoundlyConsulting\Shops');

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
        'RoundlyConsulting\Money',
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
    ])
    // ShopsFake asserts with PHPUnit, which every Laravel app has in require-dev; src/Testing
    // is only loaded by a host's test suite. Pest's arch layer cannot match a PHPUnit class
    // as an allowed root, so it is named here, exactly — nothing else from PHPUnit is permitted.
    ->ignoring('PHPUnit\Framework\Assert');

it('exposes a single public execute method on every action', function (): void {
    $paths = collect(shopsPhpFilesIn(__DIR__.'/../src/Actions'));

    // Pinned by count so the scan cannot silently cover nothing.
    expect($paths)->toHaveCount(12);

    foreach ($paths as $file) {
        $contents = (string) file_get_contents($file->getPathname());

        expect(preg_match('/^namespace (.+);/m', $contents, $ns))->toBe(1);

        $class = $ns[1].'\\'.$file->getBasename('.php');

        $publicMethods = array_filter(
            (new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC),
            fn (ReflectionMethod $m): bool => $m->class === $class && $m->getName() !== '__construct',
        );

        $names = array_values(array_map(fn (ReflectionMethod $m): string => $m->getName(), $publicMethods));

        expect($names)->toBe(['execute'], "{$class} should expose only execute()");
    }
});

/**
 * money-for-laravel is a hard dependency, but only its public surface is: its cast
 * implementations, bcmath gateway and schema internals are `@internal`. Shops goes through
 * AsMoney / AsCurrency / Money / Currency / Discount / DiscountAllocator / TaxRate only.
 */
it('does not import a money class marked @internal', function (): void {
    $internal = [];

    foreach (shopsPhpFilesIn(__DIR__.'/../vendor/roundly-consulting/money-for-laravel/src') as $file) {
        $contents = (string) file_get_contents($file->getPathname());

        // Class-level only: money also tags single methods of public classes.
        if (preg_match('/@internal\b[^\n]*\n(?:\s*\*[^\n]*\n)*\s*\*\/\s*\n(?:#\[[^\n]*\]\s*\n)*(?:(?:final|abstract|readonly)\s+)*(?:class|interface|trait|enum)\s/', $contents) !== 1
            || preg_match('/^namespace\s+([^;]+);/m', $contents, $namespace) !== 1) {
            continue;
        }

        $internal[] = $namespace[1].'\\'.$file->getBasename('.php');
    }

    // Pinned by name so the scan cannot silently cover nothing.
    expect($internal)
        ->toContain('RoundlyConsulting\Money\Casts\MoneyCast')
        ->toContain('RoundlyConsulting\Money\Math\Calculator')
        ->not->toContain('RoundlyConsulting\Money\Money');

    $offenders = [];

    foreach ([...shopsPhpFilesIn(__DIR__.'/../src'), ...shopsPhpFilesIn(__DIR__.'/../database')] as $file) {
        $contents = (string) file_get_contents($file->getPathname());

        foreach ($internal as $class) {
            if (str_contains($contents, 'use '.$class.';')) {
                $offenders[] = $file->getBasename().' → '.$class;
            }
        }
    }

    expect($offenders)->toBe([]);
});

/**
 * @return list<SplFileInfo>
 */
function shopsPhpFilesIn(string $directory): array
{
    $files = [];

    /** @var iterable<SplFileInfo> $iterator */
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file;
        }
    }

    return $files;
}
