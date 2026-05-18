<?php

namespace App\Http\Controllers;

use App\Services\TrackedInventoryStore;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(TrackedInventoryStore $store): Response
    {
        $urls = [
            [
                'loc' => route('public.index'),
                'changefreq' => 'hourly',
                'priority' => '1.0',
            ],
        ];

        foreach ($store->publicInventories() as $inv) {
            $urls[] = [
                'loc' => route('public.show', $inv->id),
                'changefreq' => 'daily',
                'priority' => '0.8',
                'lastmod' => $inv->last_checked_at ?? null,
            ];
        }

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
