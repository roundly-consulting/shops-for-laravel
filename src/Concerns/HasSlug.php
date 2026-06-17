<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Generates a URL-friendly slug from a source attribute when a model is created
 * without one, using native Laravel string helpers and no third-party
 * sluggable dependency.
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
        if (! blank($this->getAttribute($this->slugColumn()))) {
            return;
        }

        $source = (string) $this->getAttribute($this->slugSource());

        $this->setAttribute($this->slugColumn(), Str::slug($source));
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
