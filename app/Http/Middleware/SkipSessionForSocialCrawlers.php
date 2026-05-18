<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SkipSessionForSocialCrawlers
{
    /**
     * Bot chia sẻ (FB, Zalo, X…) không cần session — tránh cookie gây cache/preview lỗi.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ua = (string) $request->userAgent();

        if ($ua !== '' && preg_match(
            '/facebookexternalhit|Facebot|Twitterbot|LinkedInBot|Slackbot|WhatsApp|TelegramBot|Discordbot|Zalo/i',
            $ua
        )) {
            config([
                'session.driver' => 'array',
            ]);
        }

        return $next($request);
    }
}
