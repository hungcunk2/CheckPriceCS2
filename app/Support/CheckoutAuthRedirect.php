<?php

namespace App\Support;

class CheckoutAuthRedirect
{
    public static function isCheckoutUrl(?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '') {
            return false;
        }

        if (! str_starts_with($url, url('/'))) {
            return false;
        }

        $path = rtrim((string) (parse_url($url, PHP_URL_PATH) ?? ''), '/');

        return str_ends_with($path, '/thanh-toan');
    }

    public static function sanitize(?string $url): ?string
    {
        $url = trim((string) $url);

        return $url !== '' && str_starts_with($url, url('/')) ? $url : null;
    }

    public static function forPlan(string $plan): string
    {
        $plan = strtolower($plan);
        if (! SubscriptionPlans::exists($plan)) {
            $plan = 'max';
        }

        return route('public.checkout', ['plan' => $plan]);
    }
}
