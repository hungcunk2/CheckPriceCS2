<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('excerpt', 500);
            $table->longText('content');
            $table->date('published_at');
            $table->string('read_time', 32)->default('5 phút');
            $table->json('tags')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        foreach (config('blog.posts', []) as $post) {
            \App\Models\BlogPost::query()->create([
                'slug' => $post['slug'],
                'title' => $post['title'],
                'excerpt' => $post['excerpt'],
                'content' => $post['content'],
                'published_at' => $post['date'],
                'read_time' => $post['read_time'] ?? '5 phút',
                'tags' => $post['tags'] ?? [],
                'is_published' => true,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
