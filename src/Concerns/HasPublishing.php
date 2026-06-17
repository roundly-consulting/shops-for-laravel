<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Adds `published`/`unpublished` query scopes for models with a nullable
 * `published_at` timestamp. A record is published when `published_at` is set and
 * not in the future.
 *
 * @phpstan-require-extends Model
 */
trait HasPublishing
{
    /**
     * @param  Builder<static>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeUnpublished(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->whereNull('published_at')->orWhere('published_at', '>', now());
        });
    }

    public function isPublished(): bool
    {
        $publishedAt = $this->getAttribute('published_at');

        return $publishedAt !== null && $publishedAt->lessThanOrEqualTo(now());
    }
}
