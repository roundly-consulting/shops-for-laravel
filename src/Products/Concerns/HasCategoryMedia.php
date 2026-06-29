<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Products\Concerns;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\MediaLibrary\Buckets\MediaBucket;
use RoundlyConsulting\MediaLibrary\Concerns\InteractsWithMedia;
use RoundlyConsulting\MediaLibrary\Models\Media;

/**
 * A single public `banner` image for the bundled Category model, built on
 * media-library. Adds a `bannerUrl()` reader for category landing pages.
 *
 * @mixin Model
 */
trait HasCategoryMedia
{
    use InteractsWithMedia;

    /** Web image formats accepted by the banner bucket. */
    private const IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/avif',
    ];

    public function registerMediaBuckets(): void
    {
        $this->configureCategoryMediaBucket(
            $this->addMediaBucket($this->bannerBucket())
                ->singleFile()
                ->acceptsMimeTypes(self::IMAGE_MIME_TYPES),
        );
    }

    public function banner(): ?Media
    {
        return $this->getFirstMedia($this->bannerBucket());
    }

    public function bannerUrl(string $variant = ''): string
    {
        return $this->getFirstMediaUrl($this->bannerBucket(), $variant);
    }

    public function bannerBucket(): string
    {
        return (string) config('shops.media.banner_bucket', 'banner');
    }

    private function configureCategoryMediaBucket(MediaBucket $bucket): MediaBucket
    {
        $disk = config('shops.media.disk');

        if (is_string($disk) && $disk !== '') {
            $bucket->useDisk($disk);
        }

        if ((bool) config('shops.media.public', true)) {
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
