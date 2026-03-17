<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\StartNextSeason::class,
        \App\Console\Commands\InitStatsForNewSeason::class,
        \App\Console\Commands\InvalidateReplayAndRestoreElo::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Automatically start new season every 3 months (quarterly)
        // Runs on the 1st day of January, April, July, and October at 00:00
        $schedule->command('season:start-next')
            ->cron('0 0 1 1,4,7,10 *')
            ->withoutOverlapping()
            ->onSuccess(function () {
                Log::info('New season started automatically');

                // Send Discord webhook notification
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
                                'color' => 0x00ff00, // Green color
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
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
    }
}
