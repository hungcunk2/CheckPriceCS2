<?php

namespace App\Services;

use App\Models\BlogPost;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BlogPostStore
{
    /**
     * @return Collection<int, object>
     */
    public function all(): Collection
    {
        return BlogPost::query()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (BlogPost $row) => $this->asObject($row));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function publishedArrays(): array
    {
        return BlogPost::query()
            ->where('is_published', true)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (BlogPost $row) => $row->toPublicArray())
            ->all();
    }

    public function find(int $id): ?object
    {
        $row = BlogPost::query()->find($id);

        return $row ? $this->asObject($row) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPublished(int $id): ?array
    {
        $row = BlogPost::query()
            ->where('is_published', true)
            ->find($id);

        return $row?->toPublicArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function related(int $id, int $limit = 2): array
    {
        return BlogPost::query()
            ->where('is_published', true)
            ->where('id', '!=', $id)
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->map(fn (BlogPost $row) => $row->toPublicArray())
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug, bool $publishedOnly = true): ?array
    {
        $query = BlogPost::query()->where('slug', $slug);

        if ($publishedOnly) {
            $query->where('is_published', true);
        }

        $row = $query->first();

        return $row?->toPublicArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function relatedBySlug(string $slug, int $limit = 2): array
    {
        return BlogPost::query()
            ->where('is_published', true)
            ->where('slug', '!=', $slug)
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->map(fn (BlogPost $row) => $row->toPublicArray())
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsert(array $attributes, ?int $id = null): object
    {
        $model = $id ? BlogPost::query()->find($id) : null;

        $payload = $this->normalizeAttributes($attributes);

        if ($payload['slug'] === '') {
            $payload['slug'] = $this->slugFromTitle($payload['title']);
        }

        if ($model) {
            $model->fill($payload);
            $model->save();
        } else {
            $model = BlogPost::query()->create($payload);
        }

        return $this->asObject($model);
    }

    public function delete(int $id): void
    {
        $row = BlogPost::query()->find($id);
        if (! $row) {
            return;
        }

        $this->deleteCoverFile($row->cover_image);
        $row->delete();
    }

    public function deleteCoverFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    public function slugFromTitle(string $title): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'post-'.time();
        }

        $slug = $base;
        $i = 2;

        while (BlogPost::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizeAttributes(array $attributes): array
    {
        $tags = $attributes['tags'] ?? [];
        if (is_string($tags)) {
            $tags = array_values(array_filter(array_map('trim', explode(',', $tags))));
        }

        $payload = [
            'slug' => (string) ($attributes['slug'] ?? ''),
            'title' => (string) ($attributes['title'] ?? ''),
            'excerpt' => (string) ($attributes['excerpt'] ?? ''),
            'content' => (string) ($attributes['content'] ?? ''),
            'published_at' => Carbon::parse($attributes['published_at'] ?? now())->toDateString(),
            'read_time' => (string) ($attributes['read_time'] ?? '5 phút'),
            'tags' => $tags,
            'is_published' => (bool) ($attributes['is_published'] ?? true),
        ];

        if (array_key_exists('cover_image', $attributes)) {
            $payload['cover_image'] = $attributes['cover_image'];
        }

        return $payload;
    }

    private function asObject(BlogPost $row): object
    {
        return (object) [
            'id' => $row->id,
            'slug' => $row->slug,
            'title' => $row->title,
            'excerpt' => $row->excerpt,
            'cover_image' => $row->cover_image,
            'cover_url' => $row->coverUrl(),
            'content' => $row->content,
            'published_at' => $row->published_at?->format('Y-m-d'),
            'read_time' => $row->read_time,
            'tags' => $row->tags ?? [],
            'is_published' => (bool) $row->is_published,
            'created_at' => $row->created_at?->toIso8601String(),
            'updated_at' => $row->updated_at?->toIso8601String(),
        ];
    }
}
