<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule) {
        // Automatically start new season every 3 months (quarterly)
        // Runs on the 1st day of January, April, July, and October at 00:00
        $schedule->command('season:start-next')
            ->cron('0 0 1 1,4,7,10 *')
            ->withoutOverlapping()
            // The command itself posts the Discord announcement (final standings
            // plus the new season); posting here as well sent it twice.
            ->onSuccess(function () {
                Log::info('New season started automatically');
            })
            ->onFailure(function () {
                Log::error('Failed to start new season automatically');
            });
    })
    ->withMiddleware(function (Middleware $middleware) {
        //
        // Visitors arrive through the Cloudflare tunnel running on this host,
        // so every request comes from the host's address. Trust only that
        // address to report the real client IP and the https scheme; anyone
        // else sending these headers is ignored.
        $middleware->trustProxies(
            at: array_map('trim', explode(',', env('TRUSTED_PROXIES', '192.168.1.177,127.0.0.1'))),
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
