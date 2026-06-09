<?php

namespace App\Http\Controllers;

use App\Support\BlogPosts;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = [
            [
                'loc' => route('public.landing'),
                'changefreq' => 'weekly',
                'priority' => '1.0',
            ],
            [
                'loc' => route('public.pricing'),
                'changefreq' => 'monthly',
                'priority' => '0.9',
            ],
            [
                'loc' => route('blog.index'),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ],
        ];

        foreach (BlogPosts::all() as $post) {
            $urls[] = [
                'loc' => route('blog.show', $post['id']),
                'changefreq' => 'monthly',
                'priority' => '0.7',
                'lastmod' => $post['date'] ?? null,
            ];
        }

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
