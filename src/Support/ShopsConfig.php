<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Support;

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\Shops\Orders\Enums\PriceType;

/**
 * Strict reads of the non-boolean `shops.*` settings. A key that is not set — absent, null or
 * blank (`''` or whitespace, a host's `KEY=`) — takes its default; a present value of the wrong
 * shape — `'twenty'` for a tax rate, a `price_type` typo, an array for a bucket — throws
 * {@see InvalidConfigurationException} naming the key. Junk is never cast to 0 (a junk tax rate
 * used to become a 0 % rate) or swapped for the default.
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
     * The configured whole-number tax rates (0..100 %) by class. A class whose rate is not set
     * (null or blank) reads as 0 %, the integer reader's default.
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

    /** The catalog disk; not set (null or blank) uses the media package's disk. */
    public static function mediaDisk(): ?string
    {
        return self::blank(config('shops.media.disk')) ? null : Config::requireString('shops.media.disk');
    }

    /**
     * The responsive width ladder in pixels; null when not set. Each entry must be a width: a
     * null or blank entry inside the list is junk, not a 1 px width.
     *
     * @return list<int>|null
     */
    public static function responsiveWidths(): ?array
    {
        if (self::blank(config('shops.media.responsive_widths'))) {
            return null;
        }

        $widths = [];

        foreach (self::map('shops.media.responsive_widths') as $index => $width) {
            $key = "shops.media.responsive_widths.{$index}";

            if (self::blank($width)) {
                throw InvalidConfigurationException::notAnInteger($key, $width);
            }

            $widths[] = Config::for([$key => $width])->integer($key, 1, min: 1);
        }

        return $widths;
    }

    /** The per-image byte cap; null (not set — null or blank) for none. */
    public static function maxFileSize(): ?int
    {
        return self::blank(config('shops.media.max_file_size'))
            ? null
            : Config::integer('shops.media.max_file_size', 1, min: 1);
    }

    /** The locale a translatable attribute falls back to (the app's when not set). */
    public static function fallbackLocale(): string
    {
        if (! self::blank(config('shops.locales.fallback'))) {
            return Config::requireString('shops.locales.fallback');
        }

        $app = config('app.fallback_locale');

        return is_string($app) && ! self::blank($app) ? $app : 'en';
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

    /** Not set: absent, null or a blank string (`''` or whitespace — a host's `KEY=`). */
    public static function blank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    private static function string(string $key, string $default): string
    {
        return self::blank(config($key)) ? $default : Config::requireString($key);
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function map(string $key): array
    {
        $value = config($key);
        $value = self::blank($value) ? [] : $value;

        if (! is_array($value)) {
            throw new InvalidConfigurationException("Configuration value [{$key}] must be an array, [".get_debug_type($value).'] given.');
        }

        return $value;
    }
}
