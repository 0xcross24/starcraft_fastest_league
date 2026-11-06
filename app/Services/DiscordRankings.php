<?php

namespace App\Services;

use App\Models\DiscordMessage;
use App\Models\Season;
use App\Models\Stats;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * One Discord message with the active season's top players, edited in place
 * whenever the standings change (never a new post per change).
 *
 * Sent by the site's own Discord app (its bot token over REST, no bot process)
 * when DISCORD_RANKINGS_BOT_TOKEN and DISCORD_RANKINGS_CHANNEL_ID are set: then
 * it shows one format with 2v2 / 3v3 buttons that flip it (clicks arrive at
 * DiscordInteractionController). Otherwise sent by the channel's
 * webhook (DISCORD_RANKINGS_WEBHOOK_URL), which cannot have buttons, so it
 * shows both formats. With neither set it does nothing.
 *
 * Runs after anything that changes the standings (refreshSoon) and every five
 * minutes from the scheduler; an edit is only sent when the standings differ
 * from the last one sent.
 */
class DiscordRankings
{
    public const KEY = 'rankings';
    public const TOP = 5;
    public const FORMATS = ['2v2', '3v3'];

    private const API = 'https://discord.com/api/v10';

    private static bool $queued = false;

    public function __construct(private EloService $elo) {}

    /** 'bot', 'webhook', or null when not configured. */
    public static function mode(): ?string
    {
        if (config('services.discord.rankings_bot_token') && config('services.discord.rankings_channel_id')) {
            return 'bot';
        }

        return config('services.discord.rankings_webhook') ? 'webhook' : null;
    }

    /**
     * Sync once this request is over (after the response is sent), however
     * many times it is called during the request.
     */
    public static function refreshSoon(): void
    {
        if (self::$queued || !self::mode()) {
            return;
        }
        self::$queued = true;

        app()->terminating(function () {
            self::$queued = false;
            app(self::class)->sync();
        });
    }

    /**
     * Bring the Discord message up to date: edit it if the standings changed,
     * post it if it is missing (never posted, or deleted in Discord).
     *
     * @return string off|unchanged|edited|posted|busy|failed
     */
    public function sync(bool $force = false): string
    {
        $mode = self::mode();
        if (!$mode) {
            return 'off';
        }

        try {
            return Cache::lock('discord-rankings', 30)->block(10, fn() => $this->syncNow($mode, $force));
        } catch (LockTimeoutException) {
            return 'busy';
        } catch (\Throwable $e) {
            Log::warning('Discord rankings: sync failed', ['error' => $e->getMessage()]);

            return 'failed';
        }
    }

    private function syncNow(string $mode, bool $force): string
    {
        $post = DiscordMessage::firstOrNew(['key' => self::KEY]);
        $standings = $this->standings();
        // The badge emojis are part of what is shown: setting them re-sends it
        $hash = hash('sha256', json_encode([$standings, config('services.discord.rankings_emojis')]));

        $current = $post->message_id && $post->via === $mode;
        if ($current && $post->hash === $hash && !$force) {
            return 'unchanged';
        }

        $changedAt = $post->hash === $hash && $post->changed_at ? $post->changed_at : now();
        $format = in_array($post->format, self::FORMATS, true) ? $post->format : self::FORMATS[0];
        $payload = $this->payload($standings, $mode, $format, $changedAt->getTimestamp());

        $status = null;
        if ($current) {
            $response = $this->send($mode, 'patch', $post->message_id, $payload);
            if ($response->successful()) {
                $status = 'edited';
            } elseif ($response->status() !== 404) {
                $response->throw();
            }
        } elseif ($post->message_id) {
            // Sent the other way (webhook before the bot was set up, or back): a
            // bot cannot edit a webhook's message, so replace it.
            $this->deleteOld($post->via, $post->message_id);
        }

        if (!$status) {
            $response = $this->send($mode, 'post', null, $payload)->throw();
            $post->message_id = (string) $response->json('id');
            $post->via = $mode;
            $status = 'posted';
        }

        $post->fill(['hash' => $hash, 'changed_at' => $changedAt, 'format' => $format])->save();

        return $status;
    }

    /**
     * The message showing $format, for a button click. Remembered as the
     * format on show when it is for the rankings message itself.
     */
    public function show(string $format, ?string $messageId): array
    {
        $post = DiscordMessage::firstWhere('key', self::KEY);
        if ($post && $messageId && $post->message_id === $messageId) {
            $post->update(['format' => $format]);
        }

        $changedAt = $post?->changed_at?->getTimestamp() ?? now()->getTimestamp();

        return $this->payload($this->standings(), 'bot', $format, $changedAt);
    }

    /** What the message shows: the active season's top TOP per format. */
    public function standings(): array
    {
        $season = Season::where('is_active', 1)->first();
        if (!$season) {
            return ['season' => null, 'formats' => []];
        }

        $formats = [];
        $counts = [];
        foreach (self::FORMATS as $format) {
            $counts[$format] = Stats::where('season_id', $season->id)->where('format', $format)->ranked()->count();
            $formats[$format] = Stats::with('user')
                ->where('season_id', $season->id)
                ->where('format', $format)
                ->ranked()
                ->orderByDesc('elo')
                ->orderBy('id')
                ->limit(self::TOP)
                ->get()
                ->map(fn(Stats $s) => [
                    'name' => $s->user?->player_name ?? 'Unknown',
                    'elo' => (int) $s->elo,
                    'wins' => (int) $s->wins,
                    'losses' => (int) $s->losses,
                ])->all();
        }

        return ['season' => $season->id, 'formats' => $formats, 'counts' => $counts];
    }

    public function payload(array $standings, string $mode, string $format, int $changedAt): array
    {
        $link = fn(string $f, string $label) => ['type' => 2, 'style' => 5, 'label' => $label, 'url' => route('rankings', ['format' => $f])];

        if ($mode === 'bot') {
            $buttons = array_map(fn($f) => [
                'type' => 2,
                'style' => $f === $format ? 1 : 2,
                'label' => $f,
                'custom_id' => "rankings:{$f}",
                'disabled' => $f === $format,
            ], self::FORMATS);
            $embeds = [$this->embed($standings, $format, $changedAt)];
            $components = [['type' => 1, 'components' => [...$buttons, $link($format, 'Full rankings')]]];
        } else {
            $embeds = array_map(fn($f) => $this->embed($standings, $f, $changedAt), self::FORMATS);
            $components = [['type' => 1, 'components' => array_map(fn($f) => $link($f, "Full {$f} rankings"), self::FORMATS)]];
        }

        return [
            'content' => '',
            'embeds' => $embeds,
            'components' => $components,
            'allowed_mentions' => ['parse' => []],
        ];
    }

    private function embed(array $standings, string $format, int $changedAt): array
    {
        $season = $standings['season'];
        $url = route('rankings', ['format' => $format]);

        if (!$season) {
            $lines = ['No season is running.'];
        } else {
            $lines = [];
            foreach ($standings['formats'][$format] ?? [] as $i => $row) {
                $lines[] = sprintf('`#%d` %s **%s** — %d · %dW %dL', $i + 1, $this->badge($this->elo->getEloGrade($row['elo'])),
                    $this->escape($row['name']), $row['elo'], $row['wins'], $row['losses']);
            }
            $lines = $lines ?: ['No ranked games yet this season.'];
        }

        $lines[] = '';
        $lines[] = "-# updated <t:{$changedAt}:R>";

        return [
            'title' => $season ? "🏆 Ladder · Season {$season} · {$format} (" . ($standings['counts'][$format] ?? 0) . ')' : "🏆 {$format} Ladder",
            'url' => $url,
            'color' => $format === '2v2' ? 0xF59E0B : 0x3B82F6,
            'description' => implode("\n", $lines),
            'footer' => ['text' => 'StarCraft Fastest League · starcraftfastest.us'],
        ];
    }

    /**
     * The grade as the ladder badge emoji (sfl_D_plus for D+ ...), taken from
     * DISCORD_RANKINGS_EMOJIS: the emojis as Discord writes them, e.g.
     * "<:sfl_S:123> <:sfl_D_plus:456>" (type \:sfl_S: in Discord to get one).
     * A grade without an emoji is shown as plain text.
     */
    private function badge(string $grade): string
    {
        static $parsed = [];
        $config = (string) config('services.discord.rankings_emojis');
        if (!isset($parsed[$config])) {
            preg_match_all('/<a?:(\w+):(\d+)>/', $config, $m, PREG_SET_ORDER);
            $parsed[$config] = collect($m)->mapWithKeys(fn($e) => [$e[1] => $e[0]])->all();
        }

        $name = 'sfl_' . str_replace(['+', '-'], ['_plus', '_minus'], $grade);

        return $parsed[$config][$name] ?? "`{$grade}`";
    }

    /** Player names are shown as typed, not as Discord markdown. */
    private function escape(string $name): string
    {
        return preg_replace('/([\\\\`*_~|>\[\]()<#:])/', '\\\\$1', $name);
    }

    private function send(string $mode, string $method, ?string $messageId, array $payload): Response
    {
        if ($mode === 'bot') {
            $url = self::API . '/channels/' . config('services.discord.rankings_channel_id') . '/messages'
                . ($messageId ? "/{$messageId}" : '');

            return Http::timeout(10)->withToken(config('services.discord.rankings_bot_token'), 'Bot')->$method($url, $payload);
        }

        $url = rtrim(config('services.discord.rankings_webhook'), '/')
            . ($messageId ? "/messages/{$messageId}" : '')
            . ($messageId ? '?with_components=true' : '?wait=true&with_components=true');

        return Http::timeout(10)->$method($url, $payload);
    }

    /** Best effort: the old message may already be gone. */
    private function deleteOld(?string $via, string $messageId): void
    {
        try {
            if ($via === 'webhook' && config('services.discord.rankings_webhook')) {
                Http::timeout(10)->delete(rtrim(config('services.discord.rankings_webhook'), '/') . "/messages/{$messageId}");
            } elseif ($via === 'bot' && config('services.discord.rankings_bot_token') && config('services.discord.rankings_channel_id')) {
                Http::timeout(10)->withToken(config('services.discord.rankings_bot_token'), 'Bot')
                    ->delete(self::API . '/channels/' . config('services.discord.rankings_channel_id') . "/messages/{$messageId}");
            }
        } catch (\Throwable $e) {
            Log::info('Discord rankings: could not delete the old message', ['error' => $e->getMessage()]);
        }
    }
}
