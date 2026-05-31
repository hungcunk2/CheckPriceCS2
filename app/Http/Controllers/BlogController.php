<?php

namespace App\Http\Controllers;

use App\Support\BlogPosts;
use App\Support\SiteMeta;
use Illuminate\Http\RedirectResponse;
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

    public function show(int $post): View
    {
        $article = BlogPosts::find($post);
        abort_unless($article, 404);

        return view('blog.show', [
            'post' => $article,
            'related' => BlogPosts::related($post),
            'meta' => SiteMeta::make([
                'title' => $article['title'].' — Blog — '.config('site.name'),
                'description' => $article['excerpt'],
                'canonical' => route('blog.show', $article['id']),
                'url' => route('blog.show', $article['id']),
                'type' => 'article',
            ]),
        ]);
    }

    public function redirectFromSlug(string $slug): RedirectResponse|View
    {
        $post = BlogPosts::findBySlug($slug);
        abort_unless($post, 404);

        return redirect()->route('blog.show', $post['id'], 301);
    }
}
