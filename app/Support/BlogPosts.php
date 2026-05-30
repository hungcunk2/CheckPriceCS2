<?php

namespace App\Support;

class BlogPosts
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return config('blog.posts', []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $slug): ?array
    {
        foreach (self::all() as $post) {
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
        $related = [];

        foreach (self::all() as $post) {
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
}
