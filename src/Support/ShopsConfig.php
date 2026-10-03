<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Support;

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\Shops\Orders\Enums\PriceType;

/**
 * Strict reads of the non-boolean `shops.*` settings. An absent (null) key takes its default;
 * a present value of the wrong shape — `'twenty'` for a tax rate, a `price_type` typo, a blank
 * bucket — throws {@see InvalidConfigurationException} naming the key. Nothing is cast to 0
 * (a junk tax rate used to become a 0 % rate) or swapped for the default.
 *
 * @internal
 */
final class ShopsConfig
{
    public static function priceType(): PriceType
    {
        return Config::enum('shops.pricing.price_type', PriceType::class, PriceType::Gross);
    }

    /** The ISO 4217 code new orders, carts and prices default to. */
    public static function defaultCurrency(): string
    {
        return self::string('shops.pricing.default_currency', 'EUR');
    }

    /** The available-stock level that fires StockRanLow; at least 0. */
    public static function lowStockThreshold(): int
    {
        return Config::integer('shops.inventory.low_stock_threshold', 0, min: 0);
    }

    /**
     * The configured whole-number tax rates (0..100 %) by class.
     *
     * @return array<string, int>
     */
    public static function taxRates(): array
    {
        $rates = [];

        foreach (self::map('shops.tax_classes') as $class => $rate) {
            $rates[(string) $class] = Config::for(["shops.tax_classes.{$class}" => $rate])
                ->integer("shops.tax_classes.{$class}", 0, min: 0, max: 100);
        }

        return $rates;
    }

    public static function featuredBucket(): string
    {
        return self::string('shops.media.featured_bucket', 'featured');
    }

    public static function galleryBucket(): string
    {
        return self::string('shops.media.gallery_bucket', 'gallery');
    }

    public static function variantBucket(): string
    {
        return self::string('shops.media.variant_bucket', 'gallery');
    }

    public static function bannerBucket(): string
    {
        return self::string('shops.media.banner_bucket', 'banner');
    }

    /** The catalog disk; null uses the media package's disk. */
    public static function mediaDisk(): ?string
    {
        return config('shops.media.disk') === null ? null : Config::requireString('shops.media.disk');
    }

    /**
     * The responsive width ladder in pixels; null when unset.
     *
     * @return list<int>|null
     */
    public static function responsiveWidths(): ?array
    {
        if (config('shops.media.responsive_widths') === null) {
            return null;
        }

        $widths = [];

        foreach (self::map('shops.media.responsive_widths') as $index => $width) {
            $widths[] = Config::for(["shops.media.responsive_widths.{$index}" => $width])
                ->integer("shops.media.responsive_widths.{$index}", 1, min: 1);
        }

        return $widths;
    }

    /** The per-image byte cap; null for none. */
    public static function maxFileSize(): ?int
    {
        return config('shops.media.max_file_size') === null
            ? null
            : Config::integer('shops.media.max_file_size', 1, min: 1);
    }

    /** The locale a translatable attribute falls back to (the app's when unset). */
    public static function fallbackLocale(): string
    {
        if (config('shops.locales.fallback') !== null) {
            return Config::requireString('shops.locales.fallback');
        }

        $app = config('app.fallback_locale');

        return is_string($app) && $app !== '' ? $app : 'en';
    }

    public static function storeCreditBucket(): string
    {
        return self::string('shops.payment.store_credit_bucket', 'store_credit');
    }

    /**
     * The product attribute definitions by name.
     *
     * @return array<string, array<array-key, mixed>>
     */
    public static function attributeDefinitions(): array
    {
        $definitions = [];

        foreach (self::map('shops.attributes.definitions') as $name => $definition) {
            if (! is_array($definition)) {
                throw new InvalidConfigurationException("Configuration value [shops.attributes.definitions.{$name}] must be an array, [".get_debug_type($definition).'] given.');
            }

            $definitions[(string) $name] = $definition;
        }

        return $definitions;
    }

    private static function string(string $key, string $default): string
    {
        return config($key) === null ? $default : Config::requireString($key);
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function map(string $key): array
    {
        $value = config($key) ?? [];

        if (! is_array($value)) {
            throw new InvalidConfigurationException("Configuration value [{$key}] must be an array, [".get_debug_type($value).'] given.');
        }

        return $value;
    }
}
