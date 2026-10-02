<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Products\Concerns;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\MediaLibrary\Buckets\MediaBucket;
use RoundlyConsulting\MediaLibrary\Concerns\InteractsWithMedia;
use RoundlyConsulting\MediaLibrary\Models\Media;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * The `shops.media` settings every catalog bucket shares — product featured/gallery, variant
 * gallery and category banner alike: the accepted web image types, the disk, public/private
 * visibility, the responsive width ladder and the `max_file_size` cap. Plus the URL rule the
 * catalog readers share: a named variant that has not been generated (yet) serves the original
 * image rather than failing the page.
 *
 * @mixin Model
 */
trait ConfiguresCatalogMedia
{
    use InteractsWithMedia;

    /**
     * Web image formats accepted by every catalog bucket.
     *
     * @return list<string>
     */
    protected function catalogImageMimeTypes(): array
    {
        return ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'];
    }

    protected function configureCatalogMediaBucket(MediaBucket $bucket): MediaBucket
    {
        $bucket->acceptsMimeTypes($this->catalogImageMimeTypes());

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

        $bucket->responsiveWidths(is_array($widths) ? $this->catalogWidths($widths) : null);

        $maxSize = config('shops.media.max_file_size');

        if (is_numeric($maxSize) && (int) $maxSize > 0) {
            $bucket->maxFileSize((int) $maxSize);
        }

        return $bucket;
    }

    /**
     * The URL of a catalog image: the named variant when it has been generated, else the
     * original. An empty bucket gives its fallback URL, else ''.
     */
    protected function catalogMediaUrl(string $bucket, ?Media $media, string $variant = ''): string
    {
        if ($media === null) {
            return $this->getFirstMediaUrl($bucket);
        }

        return $media->getUrl($variant !== '' && $media->hasGeneratedVariant($variant) ? $variant : '');
    }

    /**
     * @param  array<array-key, mixed>  $widths
     * @return list<int>
     */
    private function catalogWidths(array $widths): array
    {
        $clean = [];

        foreach ($widths as $width) {
            if (is_int($width) && $width > 0) {
                $clean[] = $width;
            }
        }

        return $clean;
    }
}
