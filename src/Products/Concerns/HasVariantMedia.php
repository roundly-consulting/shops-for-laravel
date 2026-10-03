<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Products\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\MediaLibrary\Models\Media;
use RoundlyConsulting\Shops\Support\ShopsConfig;

/**
 * Per-variant catalog media built on media-library, so a colour/size variant can
 * show its own photo independently of the product gallery. Declares a single
 * `gallery` bucket — configured from `shops.media` like every catalog bucket
 * ({@see ConfiguresCatalogMedia}) — and `variantImageUrl()` readers.
 *
 * @mixin Model
 */
trait HasVariantMedia
{
    use ConfiguresCatalogMedia;

    public function registerMediaBuckets(): void
    {
        $this->configureCatalogMediaBucket(
            $this->addMediaBucket($this->variantGalleryBucket()),
        );
    }

    /** @return Collection<int, Media> */
    public function variantImages(): Collection
    {
        return $this->getMedia($this->variantGalleryBucket());
    }

    public function variantImageUrl(string $variant = ''): string
    {
        return $this->catalogMediaUrl($this->variantGalleryBucket(), $this->variantImages()->first(), $variant);
    }

    /** @return list<string> */
    public function variantImageUrls(string $variant = ''): array
    {
        return array_values(
            $this->variantImages()
                ->map(fn (Media $media): string => $this->catalogMediaUrl($this->variantGalleryBucket(), $media, $variant))
                ->all(),
        );
    }

    public function variantGalleryBucket(): string
    {
        return ShopsConfig::variantBucket();
    }
}
