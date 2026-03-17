<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Season;
use App\Models\User;
use App\Models\Stats;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StartNextSeason extends Command
{
    protected $signature = 'season:start-next';
    protected $description = 'End the current season and start the next one.';

    public function handle()
    {
        // End the current active season
        $current = Season::where('is_active', 1)->first();
        if ($current) {
            $current->is_active = 0;
            $current->save();
        }

        // Create the next season
        $lastSeason = Season::orderByDesc('id')->first();
        $nextSeasonId = $lastSeason ? $lastSeason->id + 1 : 1;
        $season = new Season();
        $season->id = $nextSeasonId;
        $season->is_active = 1;
        $season->save();


        // Create uploads directory for the new season
        $seasonDir = 'uploads/season_' . $nextSeasonId;
        if (!Storage::disk('public')->exists($seasonDir)) {
            Storage::disk('public')->makeDirectory($seasonDir);
        }

        // For each user, create stats for 2v2 and 3v3 for the new season
        $users = User::all();
        $formats = ['2v2', '3v3'];
        foreach ($users as $user) {
            foreach ($formats as $format) {
                Stats::create([
                    'wins' => 0,
                    'losses' => 0,
                    'elo' => 1000,
                    'format' => $format,
                    'user_id' => $user->id,
                    'season_id' => $nextSeasonId,
                ]);
            }
        }

        $this->info("Season {$nextSeasonId} started. Previous season ended. Stats reset for all users and formats.");

        // Send Discord webhook notification
        $this->sendDiscordNotification($nextSeasonId);

        return 0;
    }

    /**
     * Send Discord webhook notification for new season
     */
    private function sendDiscordNotification($seasonId)
    {
        try {
            $webhookUrl = config('app.discord_webhook_url', env('DISCORD_WEBHOOK_URL'));

            if (!$webhookUrl) {
                $this->warn('Discord webhook URL not configured - skipping notification');
                Log::warning('Discord webhook URL not configured - skipping notification');
                return;
            }

            $response = Http::post($webhookUrl, [
                'content' => '🚀 **New Season Started!** 🚀',
                'embeds' => [
                    [
                        'title' => 'StarCraft Fastest League - New Season',
                        'description' => "Season {$seasonId} has begun! Good luck to all players!\n\n🌐 **Play Now**: [starcraftfastest.us](https://starcraftfastest.us)",
                        'url' => 'https://starcraftfastest.us',
                        'color' => 0x00ff00, // Green color
                        'timestamp' => now()->toISOString(),
                        'fields' => [
                            [
                                'name' => '🎮 Season Number',
                                'value' => "Season {$seasonId}",
                                'inline' => true
                            ],
                            [
                                'name' => '📅 Started At',
                                'value' => now()->format('F j, Y'),
                                'inline' => true
                            ],
                            [
                                'name' => '⏰ Season Ends',
                                'value' => now()->addMonths(3)->format('F j, Y'),
                                'inline' => true
                            ],
                            [
                                'name' => '🏆 Status',
                                'value' => 'All player stats have been reset to 1000 ELO',
                                'inline' => false
                            ],
                        ],
                        'footer' => [
                            'text' => 'StarCraft Fastest League • Next season starts automatically in 3 months',
                            'icon_url' => 'https://starcraftfastest.us/favicon.ico'
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                $this->info('Discord notification sent successfully!');
                Log::info('Discord webhook notification sent successfully', ['season_id' => $seasonId]);
            } else {
                $this->error('Failed to send Discord notification');
                Log::error('Failed to send Discord webhook notification', [
                    'season_id' => $seasonId,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            $this->error('Exception while sending Discord notification: ' . $e->getMessage());
            Log::error('Exception while sending Discord webhook notification', [
                'season_id' => $seasonId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
