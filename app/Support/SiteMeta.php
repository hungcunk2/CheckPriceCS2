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
    public static function forInventory(object $inventory): array
    {
        $label = InventoryDisplay::title($inventory);
        $totalCny = (float) ($inventory->last_total_cny ?? 0);
        $itemCount = (int) ($inventory->item_count ?? 0);

        $description = $itemCount > 0
            ? sprintf(
                'Kho %s: %d skin tradable, tổng tham khảo ¥%s (Buff163). Xem chi tiết trên CheckPrice CS2.',
                $label,
                $itemCount,
                number_format($totalCny, 2)
            )
            : sprintf('Kho đồ CS2 của %s trên CheckPrice CS2 — giá Buff163, VND/USD.', $label);

        $avatar = $inventory->steam_avatar_url ?? null;
        $image = is_string($avatar) && $avatar !== '' ? $avatar : self::ogImagePath();

        return self::make([
            'title' => $label.' — '.config('site.name'),
            'description' => $description,
            'canonical' => route('public.show', $inventory->id),
            'url' => route('public.show', $inventory->id),
            'type' => 'article',
            'image' => $image,
            'image_width' => is_string($avatar) && $avatar !== '' ? 184 : config('site.og_image_width'),
            'image_height' => is_string($avatar) && $avatar !== '' ? 184 : config('site.og_image_height'),
            'image_alt' => 'Avatar Steam — '.$label,
        ]);
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
            return $path;
        }

        return rtrim((string) config('site.url'), '/').'/'.ltrim($path, '/');
    }

    public static function ogImagePath(): string
    {
        $path = config('site.og_image');

        return filled($path) ? (string) $path : '/images/og-share.jpg';
    }
}
