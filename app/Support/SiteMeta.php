<?php

namespace App\Support;

class SiteMeta
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function make(array $overrides = []): array
    {
        $base = [
            'title' => config('site.title'),
            'description' => config('site.description'),
            'keywords' => config('site.keywords'),
            'url' => config('site.url'),
            'canonical' => config('site.url'),
            'image' => self::absoluteAsset(self::ogImagePath()),
            'image_width' => config('site.og_image_width'),
            'image_height' => config('site.og_image_height'),
            'image_alt' => config('site.og_image_alt'),
            'site_name' => config('site.name'),
            'locale' => config('site.locale'),
            'type' => 'website',
            'robots' => config('site.robots'),
            'twitter_card' => 'summary_large_image',
            'twitter_site' => config('site.twitter_site'),
            'twitter_creator' => config('site.twitter_creator'),
            'facebook_app_id' => config('site.facebook_app_id'),
            'theme_color' => config('site.theme_color'),
            'author' => config('site.author'),
        ];

        $meta = array_merge($base, $overrides);

        if (! empty($meta['image']) && ! str_starts_with((string) $meta['image'], 'http')) {
            $meta['image'] = self::absoluteAsset((string) $meta['image']);
        } elseif (! empty($meta['image'])) {
            $meta['image'] = self::ensureHttps((string) $meta['image']);
        }

        if (! empty($overrides['canonical'])) {
            $meta['canonical'] = (string) $overrides['canonical'];
        }

        if (! empty($overrides['url'])) {
            $meta['url'] = (string) $overrides['url'];
        }

        return $meta;
    }

    /**
     * @return array<string, mixed>
     */
    public static function forRequest(?string $pageTitle = null): array
    {
        $overrides = [
            'canonical' => url()->current(),
            'url' => url()->current(),
        ];

        if ($pageTitle !== null && trim($pageTitle) !== '') {
            $overrides['title'] = trim($pageTitle).' — '.config('site.name');
        }

        return self::make($overrides);
    }

    /**
     * @return array<string, mixed>
     */
    public static function noindex(string $title): array
    {
        return self::make([
            'title' => $title,
            'robots' => 'noindex, nofollow',
        ]);
    }

    public static function absoluteAsset(?string $path): string
    {
        $path = trim((string) ($path ?? ''));
        if ($path === '') {
            return (string) config('site.url');
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return self::ensureHttps((string) $path);
        }

        return self::ensureHttps(rtrim((string) config('site.url'), '/').'/'.ltrim($path, '/'));
    }

    public static function ensureHttps(string $url): string
    {
        return str_starts_with($url, 'http://')
            ? 'https://'.substr($url, 7)
            : $url;
    }

    /**
     * @return array{width: int, height: int}|null
     */
    public static function imageDimensions(?string $relativePublicPath): ?array
    {
        if ($relativePublicPath === null || $relativePublicPath === '') {
            return null;
        }

        $path = storage_path('app/public/'.ltrim($relativePublicPath, '/'));
        if (! is_file($path)) {
            return null;
        }

        $size = @getimagesize($path);

        if ($size === false) {
            return null;
        }

        return [
            'width' => (int) $size[0],
            'height' => (int) $size[1],
        ];
    }

    public static function ogImagePath(): string
    {
        $path = config('site.og_image');

        return filled($path) ? (string) $path : '/images/og-share.jpg';
    }

    public static function coverImageUrl(?string $coverImagePath): ?string
    {
        if ($coverImagePath === null || $coverImagePath === '') {
            return null;
        }

        return self::absoluteAsset('/storage/'.ltrim($coverImagePath, '/'));
    }

    /**
     * @param  array<string, mixed>  $article
     * @return array<string, mixed>
     */
    public static function forBlogPost(array $article): array
    {
        $meta = [
            'title' => $article['title'].' — Blog — '.config('site.name'),
            'og_title' => $article['title'],
            'description' => $article['meta_description'],
            'canonical' => route('blog.show', $article['id']),
            'url' => route('blog.show', $article['id']),
            'type' => 'article',
            'published_at' => ($article['date'] ?? '').'T00:00:00+07:00',
            'blog_post' => $article,
        ];

        $coverImage = $article['cover_image'] ?? null;
        $coverUrl = self::coverImageUrl(is_string($coverImage) ? $coverImage : null);

        if ($coverUrl !== null) {
            $meta['image'] = $coverUrl;
            $meta['image_alt'] = $article['title'];
            $dimensions = self::imageDimensions(is_string($coverImage) ? $coverImage : null);
            $meta['image_width'] = $dimensions['width'] ?? BlogCoverImageProcessor::WIDTH;
            $meta['image_height'] = $dimensions['height'] ?? BlogCoverImageProcessor::HEIGHT;
        }

        return self::make($meta);
    }
}
