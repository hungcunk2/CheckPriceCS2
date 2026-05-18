<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SkipSessionForSocialCrawlers
{
    /**
     * Bot chia sẻ (Messenger/FB, Zalo, X…) — không gửi cookie session (FB hay cache preview lỗi).
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isSocialCrawler($request)) {
            return $next($request);
        }

        config([
            'session.driver' => 'array',
        ]);

        $response = $next($request);

        return $this->withoutSessionCookies($response);
    }

    public function isSocialCrawler(Request $request): bool
    {
        $ua = (string) $request->userAgent();

        if ($ua === '') {
            return false;
        }

        return (bool) preg_match(
            '/facebookexternalhit|Facebot|Meta-ExternalAgent|meta-externalagent|facebookcatalog|'
            .'Twitterbot|LinkedInBot|Slackbot|WhatsApp|TelegramBot|Discordbot|Zalo/i',
            $ua
        );
    }

    private function withoutSessionCookies(Response $response): Response
    {
        foreach ($response->headers->getCookies() as $cookie) {
            $response->headers->removeCookie(
                $cookie->getName(),
                $cookie->getPath(),
                $cookie->getDomain(),
                $cookie->isSecure(),
                $cookie->isHttpOnly(),
                $cookie->getSameSite()
            );
        }

        return $response;
    }
}
