<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Products\Concerns;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\MediaLibrary\Models\Media;
use RoundlyConsulting\Shops\Support\ShopsConfig;

/**
 * A single public `banner` image for the bundled Category model, built on
 * media-library and configured from `shops.media` like every catalog bucket
 * ({@see ConfiguresCatalogMedia}). Adds a `bannerUrl()` reader for category
 * landing pages.
 *
 * @mixin Model
 */
trait HasCategoryMedia
{
    use ConfiguresCatalogMedia;

    public function registerMediaBuckets(): void
    {
        $this->configureCatalogMediaBucket(
            $this->addMediaBucket($this->bannerBucket())->singleFile(),
        );
    }

    public function banner(): ?Media
    {
        return $this->getFirstMedia($this->bannerBucket());
    }

    public function bannerUrl(string $variant = ''): string
    {
        return $this->catalogMediaUrl($this->bannerBucket(), $this->banner(), $variant);
    }

    public function bannerBucket(): string
    {
        return ShopsConfig::bannerBucket();
    }
}
