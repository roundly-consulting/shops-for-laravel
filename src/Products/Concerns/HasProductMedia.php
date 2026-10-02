<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Products\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\MediaLibrary\Models\Media;

/**
 * First-class catalog media for the bundled Product model, built on
 * roundly-consulting/media-library-for-laravel.
 *
 * Declares a single `featured` image and a multi-image `gallery`, both configured from
 * `shops.media` ({@see ConfiguresCatalogMedia}: public by default — catalog imagery is served
 * over a CDN for SEO — with a responsive width ladder and the size cap). Adds product-specific
 * readers for the featured image, the gallery, and an SEO/JSON-LD image that falls back to the
 * first gallery image when no featured image is set. A reader asked for a variant that is not
 * generated serves the original.
 *
 * @mixin Model
 */
trait HasProductMedia
{
    use ConfiguresCatalogMedia;

    public function registerMediaBuckets(): void
    {
        $this->configureCatalogMediaBucket(
            $this->addMediaBucket($this->featuredBucket())->singleFile(),
        );

        $this->configureCatalogMediaBucket(
            $this->addMediaBucket($this->galleryBucket()),
        );
    }

    public function featuredImage(): ?Media
    {
        return $this->getFirstMedia($this->featuredBucket());
    }

    public function featuredImageUrl(string $variant = ''): string
    {
        return $this->catalogMediaUrl($this->featuredBucket(), $this->featuredImage(), $variant);
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
                ->map(fn (Media $media): string => $this->catalogMediaUrl($this->galleryBucket(), $media, $variant))
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
}
