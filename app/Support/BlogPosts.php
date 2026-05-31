<?php

namespace App\Support;

use App\Services\BlogPostStore;
use Illuminate\Support\Facades\Schema;

class BlogPosts
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        if (self::usesDatabase()) {
            return app(BlogPostStore::class)->publishedArrays();
        }

        return config('blog.posts', []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $slug): ?array
    {
        if (self::usesDatabase()) {
            return app(BlogPostStore::class)->findBySlug($slug);
        }

        foreach (config('blog.posts', []) as $post) {
            if (($post['slug'] ?? '') === $slug) {
                return $post;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function related(string $slug, int $limit = 2): array
    {
        if (self::usesDatabase()) {
            return app(BlogPostStore::class)->related($slug, $limit);
        }

        $related = [];

        foreach (config('blog.posts', []) as $post) {
            if (($post['slug'] ?? '') === $slug) {
                continue;
            }

            $related[] = $post;

            if (count($related) >= $limit) {
                break;
            }
        }

        return $related;
    }

    private static function usesDatabase(): bool
    {
        try {
            return Schema::hasTable('blog_posts') && \App\Models\BlogPost::query()->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
