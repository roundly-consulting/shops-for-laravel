<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Concerns;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Shops\Support\ShopsConfig;

/**
 * Stores translatable attributes as native JSON maps keyed by locale, with no
 * third-party translatable dependency. Reads transparently return the current
 * application locale's value (falling back to `shops.locales.fallback`), and a
 * plain-string write is stored under the current locale.
 *
 * Implementing models list their translatable attributes in
 * `translatableAttributes()` and cast each to `array` in `casts()`.
 *
 * @phpstan-require-extends Model
 */
trait HasTranslations
{
    /**
     * @return list<string>
     */
    abstract public function translatableAttributes(): array;

    public function getTranslation(string $attribute, ?string $locale = null): ?string
    {
        $locale ??= $this->currentLocale();

        $translations = $this->translationsFor($attribute);

        return $translations[$locale]
            ?? $translations[$this->fallbackLocale()]
            ?? null;
    }

    public function setTranslation(string $attribute, string $locale, string $value): static
    {
        $translations = $this->translationsFor($attribute);
        $translations[$locale] = $value;

        $this->attributes[$attribute] = (string) json_encode($translations);

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getTranslations(string $attribute): array
    {
        return $this->translationsFor($attribute);
    }

    public function getAttributeValue($key): mixed
    {
        if (in_array($key, $this->translatableAttributes(), true)) {
            return $this->getTranslation($key);
        }

        return parent::getAttributeValue($key);
    }

    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->translatableAttributes(), true) && is_string($value)) {
            return $this->setTranslation($key, $this->currentLocale(), $value);
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * @return array<string, string>
     */
    protected function translationsFor(string $attribute): array
    {
        $raw = $this->attributes[$attribute] ?? null;

        if ($raw === null) {
            return [];
        }

        /** @var array<string, string> $decoded */
        $decoded = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);

        return $decoded;
    }

    protected function currentLocale(): string
    {
        return app()->getLocale();
    }

    protected function fallbackLocale(): string
    {
        return ShopsConfig::fallbackLocale();
    }
}
