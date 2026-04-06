<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Http;
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
            ->onSuccess(function () {
                Log::info('New season started automatically');

                try {
                    $webhookUrl = config('app.discord_webhook_url', env('DISCORD_WEBHOOK_URL'));

                    if (!$webhookUrl) {
                        Log::warning('Discord webhook URL not configured - skipping notification');
                        return;
                    }

                    $response = Http::post($webhookUrl, [
                        'content' => '🚀 **New Season Started!** 🚀',
                        'embeds' => [
                            [
                                'title' => 'StarCraft Fastest League - New Season',
                                'description' => 'A new competitive season has begun! Good luck to all players!',
                                'color' => 0x00ff00,
                                'timestamp' => now()->toISOString(),
                                'fields' => [
                                    [
                                        'name' => '🎮 Season Status',
                                        'value' => 'Season automatically started via scheduler',
                                        'inline' => false
                                    ],
                                    [
                                        'name' => '📅 Started At',
                                        'value' => now()->format('F j, Y \a\t g:i A T'),
                                        'inline' => true
                                    ]
                                ],
                                'footer' => [
                                    'text' => 'StarCraft Fastest League'
                                ]
                            ]
                        ]
                    ]);

                    if ($response->successful()) {
                        Log::info('Discord webhook notification sent successfully');
                    } else {
                        Log::error('Failed to send Discord webhook notification', [
                            'status' => $response->status(),
                            'response' => $response->body()
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Exception while sending Discord webhook notification', [
                        'error' => $e->getMessage()
                    ]);
                }
            })
            ->onFailure(function () {
                Log::error('Failed to start new season automatically');
            });
    })
    ->withMiddleware(function (Middleware $middleware) {
        //
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
