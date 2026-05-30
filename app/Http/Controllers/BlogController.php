<?php

namespace App\Http\Controllers;

use App\Support\BlogPosts;
use App\Support\SiteMeta;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        return view('blog.index', [
            'posts' => BlogPosts::all(),
            'meta' => SiteMeta::make([
                'title' => 'Blog — '.config('site.name'),
                'description' => 'Kiến thức, hướng dẫn và tin tức về CS2, skin và giao dịch.',
                'canonical' => route('blog.index'),
                'url' => route('blog.index'),
            ]),
        ]);
    }

    public function show(string $slug): View
    {
        $post = BlogPosts::find($slug);
        abort_unless($post, 404);

        return view('blog.show', [
            'post' => $post,
            'related' => BlogPosts::related($slug),
            'meta' => SiteMeta::make([
                'title' => $post['title'].' — Blog — '.config('site.name'),
                'description' => $post['excerpt'],
                'canonical' => route('blog.show', $post['slug']),
                'url' => route('blog.show', $post['slug']),
                'type' => 'article',
            ]),
        ]);
    }
}
