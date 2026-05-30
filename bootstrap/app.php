<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(prepend: [
            \App\Http\Middleware\SkipSessionForSocialCrawlers::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        if (! config('cs2price.price_auto_sync_enabled', true)) {
            return;
        }

        $minutes = max(1, (int) config('cs2price.price_auto_sync_minutes', 10));

        $schedule->command('cs2price:sync-prices')
            ->cron("*/{$minutes} * * * *")
            ->withoutOverlapping(max(60, $minutes))
            ->runInBackground();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
