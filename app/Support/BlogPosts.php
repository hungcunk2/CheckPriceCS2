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

        $posts = [];

        foreach (config('blog.posts', []) as $index => $post) {
            $posts[] = array_merge($post, ['id' => $index + 1]);
        }

        return $posts;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        if (self::usesDatabase()) {
            return app(BlogPostStore::class)->findPublished($id);
        }

        $posts = config('blog.posts', []);
        $index = $id - 1;

        if (! isset($posts[$index])) {
            return null;
        }

        return array_merge($posts[$index], ['id' => $id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findBySlug(string $slug): ?array
    {
        if (self::usesDatabase()) {
            return app(BlogPostStore::class)->findBySlug($slug);
        }

        foreach (config('blog.posts', []) as $index => $post) {
            if (($post['slug'] ?? '') === $slug) {
                return array_merge($post, ['id' => $index + 1]);
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function related(int $id, int $limit = 2): array
    {
        if (self::usesDatabase()) {
            return app(BlogPostStore::class)->related($id, $limit);
        }

        $related = [];

        foreach (self::all() as $post) {
            if (($post['id'] ?? 0) === $id) {
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
