<?php

namespace App\Models;

use App\Support\SiteMeta;
use Illuminate\Database\Eloquent\Model;

class BlogPost extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'excerpt',
        'meta_description',
        'cover_image',
        'content',
        'published_at',
        'read_time',
        'tags',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'date',
            'tags' => 'array',
            'is_published' => 'boolean',
        ];
    }

    public function coverUrl(): ?string
    {
        if (! $this->cover_image) {
            return null;
        }

        return SiteMeta::coverImageUrl($this->cover_image);
    }

    public function metaDescription(): string
    {
        $meta = trim((string) ($this->meta_description ?? ''));

        return $meta !== '' ? $meta : (string) $this->excerpt;
    }

    /**
     * @return array<string, mixed>
     */
    public function toListArray(): array
    {
        $tags = $this->tags;
        if (! is_array($tags)) {
            $tags = [];
        }

        return [
            'id' => $this->id,
            'title' => (string) ($this->title ?? ''),
            'excerpt' => (string) ($this->excerpt ?? ''),
            'meta_description' => $this->metaDescription(),
            'cover_url' => $this->coverUrl(),
            'cover_image' => $this->cover_image,
            'date' => $this->published_at?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'read_time' => (string) ($this->read_time ?? '5 phút'),
            'tags' => $tags,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return array_merge($this->toListArray(), [
            'content' => (string) ($this->content ?? ''),
        ]);
    }
}
