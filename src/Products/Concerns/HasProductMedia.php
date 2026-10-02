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
 * First-class catalog media for the bundled Product model, built on
 * roundly-consulting/media-library-for-laravel.
 *
 * Declares a single `featured` image and a multi-image `gallery`, both stored
 * with public visibility by default (catalog imagery is served over a CDN for
 * SEO) with a responsive width ladder. Adds product-specific readers for the
 * featured image, the gallery, and an SEO/JSON-LD image that falls back to the
 * first gallery image when no featured image is set.
 *
 * @mixin Model
 */
trait HasProductMedia
{
    use InteractsWithMedia;

    /** Web image formats accepted by the catalog buckets. */
    private const IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/avif',
    ];

    public function registerMediaBuckets(): void
    {
        $this->configureMediaBucket(
            $this->addMediaBucket($this->featuredBucket())
                ->singleFile()
                ->acceptsMimeTypes(self::IMAGE_MIME_TYPES),
        );

        $this->configureMediaBucket(
            $this->addMediaBucket($this->galleryBucket())
                ->acceptsMimeTypes(self::IMAGE_MIME_TYPES),
        );
    }

    public function featuredImage(): ?Media
    {
        return $this->getFirstMedia($this->featuredBucket());
    }

    public function featuredImageUrl(string $variant = ''): string
    {
        return $this->getFirstMediaUrl($this->featuredBucket(), $variant);
    }

    /** @return Collection<int, Media> */
    public function galleryImages(): Collection
    {
        return $this->getMedia($this->galleryBucket());
    }

    /** @return list<string> */
    public function galleryUrls(string $variant = ''): array
    {
        return array_values(
            $this->galleryImages()
                ->map(fn (Media $media): string => $media->getUrl($variant))
                ->all(),
        );
    }

    /**
     * The image for og:image / schema.org Product.image: the featured image, or
     * the first gallery image when no featured image exists. Empty string when
     * the product has no imagery.
     */
    public function seoImageUrl(string $variant = ''): string
    {
        $featured = $this->featuredImageUrl($variant);

        if ($featured !== '') {
            return $featured;
        }

        return $this->galleryUrls($variant)[0] ?? '';
    }

    public function featuredBucket(): string
    {
        return (string) config('shops.media.featured_bucket', 'featured');
    }

    public function galleryBucket(): string
    {
        return (string) config('shops.media.gallery_bucket', 'gallery');
    }

    private function configureMediaBucket(MediaBucket $bucket): MediaBucket
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

        $bucket->responsiveWidths(
            is_array($widths) ? $this->normalizeWidths($widths) : null,
        );

        $maxSize = config('shops.media.max_file_size');

        if (is_int($maxSize) && $maxSize > 0) {
            $bucket->maxFileSize($maxSize);
        }

        return $bucket;
    }

    /**
     * @param  array<array-key, mixed>  $widths
     * @return list<int>
     */
    private function normalizeWidths(array $widths): array
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
