<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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

        return Storage::disk('public')->url($this->cover_image);
    }

    public function metaDescription(): string
    {
        $meta = trim((string) ($this->meta_description ?? ''));

        return $meta !== '' ? $meta : (string) $this->excerpt;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'meta_description' => $this->metaDescription(),
            'cover_url' => $this->coverUrl(),
            'content' => $this->content,
            'date' => $this->published_at->format('Y-m-d'),
            'read_time' => $this->read_time,
            'tags' => $this->tags ?? [],
        ];
    }
}
