<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Season;
use App\Models\PlayerName;
use App\Models\Stats;
use App\Services\EloService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StartNextSeason extends Command
{
    protected $signature = 'season:start-next
                            {--preview : Print the Discord message for the current season without changing anything}';
    protected $description = 'End the current season and start the next one.';

    public const STANDINGS_SIZE = 6;

    public function handle()
    {
        $current = Season::where('is_active', 1)->first();

        if ($this->option('preview')) {
            $nextId = (Season::max('id') ?? 0) + 1;
            $this->line(json_encode($this->discordPayload($nextId, $current), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->info('Preview only: nothing was changed or sent.');
            return 0;
        }

        // End the current active season
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

        // Every player name starts the new season on fresh 2v2 and 3v3 stats
        foreach (PlayerName::all() as $playerName) {
            $playerName->ensureStats($season);
        }

        $this->info("Season {$nextSeasonId} started. Previous season ended. Stats reset for all users and formats.");

        // One Discord message: the ended season's final top players, then the new season
        $this->sendDiscordNotification($nextSeasonId, $this->discordPayload($nextSeasonId, $current));

        return 0;
    }

    /**
     * Send Discord webhook notification for new season
     */
    private function sendDiscordNotification($seasonId, array $payload)
    {
        try {
            $webhookUrl = config('app.discord_webhook_url', env('DISCORD_WEBHOOK_URL'));

            if (!$webhookUrl) {
                $this->warn('Discord webhook URL not configured - skipping notification');
                Log::warning('Discord webhook URL not configured - skipping notification');
                return;
            }

            $response = Http::post($webhookUrl, $payload);

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

    /**
     * The final top players of $ended (if any games were played), followed by
     * the new season announcement.
     */
    private function discordPayload(int $seasonId, ?Season $ended): array
    {
        $embeds = [];

        if ($ended && ($standings = $this->standingsEmbed($ended))) {
            $embeds[] = $standings;
        }

        $embeds[] = [
            'title' => 'StarCraft Fastest League - New Season',
            'description' => "Season {$seasonId} has begun! Good luck to all players!\n\n🌐 **Play Now**: [starcraftfastest.us](https://starcraftfastest.us)",
            'url' => 'https://starcraftfastest.us',
            'color' => 0x00ff00, // Green color
            'timestamp' => now()->toISOString(),
            'fields' => [
                ['name' => '🎮 Season Number', 'value' => "Season {$seasonId}", 'inline' => true],
                ['name' => '📅 Started At', 'value' => now()->format('F j, Y'), 'inline' => true],
                ['name' => '⏰ Season Ends', 'value' => now()->addMonths(3)->format('F j, Y'), 'inline' => true],
                ['name' => '🏆 Status', 'value' => 'All player stats have been reset to 1000 ELO', 'inline' => false],
            ],
            'footer' => [
                'text' => 'StarCraft Fastest League • Next season starts automatically in 3 months',
                'icon_url' => 'https://starcraftfastest.us/favicon.ico',
            ],
        ];

        return ['content' => '🚀 **New Season Started!** 🚀', 'embeds' => $embeds];
    }

    /**
     * Top STANDINGS_SIZE per format, ordered like the rankings page (elo, then
     * stats id). Names with no games that season are left out.
     */
    private function standingsEmbed(Season $season): ?array
    {
        $grades = app(EloService::class);
        $medals = ['🥇', '🥈', '🥉'];
        $fields = [];

        foreach (PlayerName::FORMATS as $format) {
            $top = Stats::with('playerName')
                ->where('season_id', $season->id)
                ->where('format', $format)
                ->whereRaw('wins + losses > 0')
                ->orderByDesc('elo')
                ->orderBy('id')
                ->limit(self::STANDINGS_SIZE)
                ->get();

            if ($top->isEmpty()) {
                continue;
            }

            $lines = $top->values()->map(fn(Stats $stat, int $i) => sprintf(
                '%s **%s** — %d ELO (%s) · %d-%d',
                $medals[$i] ?? '`#' . ($i + 1) . '`',
                $this->escapeMarkdown($stat->playerName?->name ?? 'Unknown'),
                $stat->elo,
                $grades->getEloGrade($stat->elo),
                $stat->wins,
                $stat->losses,
            ));

            $fields[] = ['name' => "🏆 {$format} Top " . self::STANDINGS_SIZE, 'value' => $lines->implode("\n"), 'inline' => false];
        }

        if (!$fields) {
            return null;
        }

        return [
            'title' => "Season {$season->id} Final Standings",
            'url' => 'https://starcraftfastest.us/rankings?season=' . $season->id,
            'color' => 0xf1c40f, // Gold
            'fields' => $fields,
        ];
    }

    /**
     * Player names like Wk`Cross- or Clan_[SFL] would otherwise be read as
     * Discord formatting.
     */
    private function escapeMarkdown(string $text): string
    {
        return preg_replace('/([\\\\`*_~|>\[\]()#-])/', '\\\\$1', $text);
    }
}
