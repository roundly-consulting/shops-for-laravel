<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Products\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\MediaLibrary\Buckets\MediaBucket;
use RoundlyConsulting\MediaLibrary\Concerns\InteractsWithMedia;
use RoundlyConsulting\MediaLibrary\Models\Media;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * Per-variant catalog media built on media-library, so a colour/size variant can
 * show its own photo independently of the product gallery. Declares a single
 * `gallery` bucket and a `variantImageUrl()` reader.
 *
 * @mixin Model
 */
trait HasVariantMedia
{
    use InteractsWithMedia;

    /** Web image formats accepted by the variant gallery. */
    private const IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/avif',
    ];

    public function registerMediaBuckets(): void
    {
        $this->configureVariantMediaBucket(
            $this->addMediaBucket($this->variantGalleryBucket())
                ->acceptsMimeTypes(self::IMAGE_MIME_TYPES),
        );
    }

    /** @return Collection<int, Media> */
    public function variantImages(): Collection
    {
        return $this->getMedia($this->variantGalleryBucket());
    }

    public function variantImageUrl(string $variant = ''): string
    {
        return $this->getFirstMediaUrl($this->variantGalleryBucket(), $variant);
    }

    /** @return list<string> */
    public function variantImageUrls(string $variant = ''): array
    {
        return array_values(
            $this->variantImages()
                ->map(fn (Media $media): string => $media->getUrl($variant))
                ->all(),
        );
    }

    public function variantGalleryBucket(): string
    {
        return (string) config('shops.media.variant_bucket', 'gallery');
    }

    private function configureVariantMediaBucket(MediaBucket $bucket): MediaBucket
    {
        $disk = config('shops.media.disk');

        if (is_string($disk) && $disk !== '') {
            $bucket->useDisk($disk);
        }

        if (Config::boolean('shops.media.public', true)) {
            $bucket->public();
        } else {
            $bucket->private();
        }

        $widths = config('shops.media.responsive_widths');

        if (is_array($widths)) {
            $clean = [];

            foreach ($widths as $width) {
                if (is_int($width) && $width > 0) {
                    $clean[] = $width;
                }
            }

            $bucket->responsiveWidths($clean);
        }

        return $bucket;
    }
}
