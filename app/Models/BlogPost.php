<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogPost extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'excerpt',
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

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'date' => $this->published_at->format('Y-m-d'),
            'read_time' => $this->read_time,
            'tags' => $this->tags ?? [],
        ];
    }
}
