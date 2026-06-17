<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Contracts;

use RoundlyConsulting\Shops\Concerns\HasTranslations;

/**
 * Implemented by models that store translatable attributes as per-locale JSON
 * maps via {@see HasTranslations}.
 */
interface Translatable
{
    /**
     * @return list<string>
     */
    public function translatableAttributes(): array;

    public function getTranslation(string $attribute, ?string $locale = null): ?string;

    public function setTranslation(string $attribute, string $locale, string $value): static;

    /**
     * @return array<string, string>
     */
    public function getTranslations(string $attribute): array;
}
