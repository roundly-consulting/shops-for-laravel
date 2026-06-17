<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RoundlyConsulting\Shops\Contracts\Translatable;

/**
 * Generates a URL-friendly slug from a source attribute when a model is created
 * without one, using native Laravel string helpers and no third-party
 * sluggable dependency.
 *
 * When the model also uses {@see HasTranslations} and its slug column is
 * translatable, the slug is generated per locale from the source's translations.
 *
 * @phpstan-require-extends Model
 */
trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::creating(function (Model $model): void {
            if ($model instanceof self) {
                $model->generateSlug();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return $this->slugColumn();
    }

    protected function generateSlug(): void
    {
        if ($this instanceof Translatable && in_array($this->slugColumn(), $this->translatableAttributes(), true)) {
            $this->generateTranslatedSlug($this);

            return;
        }

        if (! blank($this->getAttribute($this->slugColumn()))) {
            return;
        }

        $source = (string) $this->getAttribute($this->slugSource());

        $this->setAttribute($this->slugColumn(), Str::slug($source));
    }

    private function generateTranslatedSlug(Translatable $model): void
    {
        $existing = $model->getTranslations($this->slugColumn());
        $sources = $model->getTranslations($this->slugSource());

        foreach ($sources as $locale => $value) {
            if (blank($existing[$locale] ?? null)) {
                $model->setTranslation($this->slugColumn(), $locale, Str::slug($value));
            }
        }
    }

    protected function slugSource(): string
    {
        return 'name';
    }

    protected function slugColumn(): string
    {
        return 'slug';
    }
}
