<?php

declare(strict_types=1);

/**
 * The config contract shops never had — and the guard for its worst shipped bug.
 *
 * #18: the whole store-credit feature read `shops.payments.*` while the file shipped
 * `shops.payment.*`. `SHOPS_ALLOW_STORE_CREDIT` did nothing, and **330 tests stayed
 * green** because the suite set the same wrong key the source read. No amount of
 * behavioural testing finds that; only pinning the shipped file against the source
 * does.
 *
 * Both directions are on:
 *  - forward — every key the code reads is shipped (that is #18);
 *  - reverse — every shipped leaf is read (a documented key nothing reads is dead
 *    config: media's `max_file_size` cap that never applied, alerts' thrice-documented
 *    `escalation` key).
 */
it('ships exactly the config keys it reads', function (): void {
    expect(__DIR__.'/../../config/shops.php')->toSatisfyConfigContract(__DIR__.'/../../src', [
        // Shops reads a class-string key through two toolkit seams rather than a
        // `config()` call — `PackageServiceProvider::bindFromConfig(Contract::class,
        // 'shops.tax.resolver', …)` and `ModelResolver::for('shops.shop_model', …)`.
        // Both are real reads that drive real bindings; neither is a `config(` token,
        // so the prefix is what makes them visible to the scraper.
        'extraReadPrefixes' => ['shops.'],

        // `shops.tax_classes` is a host-authored MAP (tax class name => whole-number
        // rate), read wholesale by ConfigTaxResolver and looked up by a name that comes
        // from the product, not from this file. These three are shipped defaults —
        // sample data, not a fixed schema — so no code reads them by leaf and none
        // should. `allowUnread` is rot-proof: rename one and this entry goes stale,
        // which is itself a failure.
        'allowUnread' => [
            'shops.tax_classes.standard',
            'shops.tax_classes.reduced',
            'shops.tax_classes.zero',
        ],
    ]);
});
