<?php

namespace App\Services;

use App\Models\InvalidationComment;
use App\Models\InvalidationRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Invalidation requests as Discord tickets: each game gets one post in a forum
 * channel (through that channel's webhook), and later requests for the same
 * game and the admin's decision are replies in that post.
 *
 * Discord is only the conversation; the request is saved on the site first, so
 * a missing webhook or a Discord outage never loses one (it still shows in the
 * admin portal).
 */
class DiscordTickets
{
    /**
     * Open a ticket for $request, or reply in the game's existing one.
     * Returns the post (thread) id, or null if it could not be posted.
     */
    public function open(InvalidationRequest $request): ?string
    {
        $existing = InvalidationRequest::where('replay_id', $request->replay_id)
            ->whereKeyNot($request->id)
            ->whereNotNull('discord_thread_id')
            ->value('discord_thread_id');

        if ($existing) {
            return $this->send(['embeds' => [$this->embed($request, 'Another player asked to invalidate this game')]], $existing)
                ? $existing : null;
        }

        $rows = $request->rows();
        $first = $rows->first();
        $payload = [
            'thread_name' => Str::limit(sprintf('Game %s · S%s %s · %s',
                substr($request->replay_id, 0, 8), $first?->season_id, $first?->format, $request->playerName?->name ?? $request->user->player_name), 100, ''),
            'embeds' => [$this->embed($request, 'Invalidation request')],
        ];
        if ($role = config('services.discord.tickets_role_id')) {
            $payload['content'] = "<@&{$role}> new invalidation request";
            $payload['allowed_mentions'] = ['parse' => [], 'roles' => [(string) $role]];
        }

        $message = $this->send($payload);

        return $message['channel_id'] ?? null;
    }

    /**
     * Post an admin's decision in the game's ticket.
     */
    public function resolved(InvalidationRequest $request, string $adminName): void
    {
        if (!$request->discord_thread_id) {
            return;
        }

        $approved = $request->status === InvalidationRequest::APPROVED;
        $this->send(['embeds' => [array_filter([
            'title' => $approved ? 'Approved: game invalidated' : 'Rejected: game stays valid',
            'color' => $approved ? 0x10B981 : 0xEF4444,
            'url' => route('games.show', $request->replay_id),
            'description' => $this->withLink($request->replay_id, $request->resolution_note ? Str::limit($request->resolution_note, 2000) : null),
            'footer' => ['text' => 'by ' . $adminName],
            'timestamp' => now()->toIso8601String(),
        ])]], $request->discord_thread_id);
    }

    /**
     * Mirror a message from the site's discussion into the game's ticket.
     */
    public function comment(?string $threadId, string $authorName, InvalidationComment $comment): void
    {
        if (!$threadId) {
            return;
        }

        [$title, $color] = match (true) {
            $comment->from_admin => ["Admin $authorName replied", 0x3B82F6],
            $comment->stance === InvalidationComment::AGREE => ["$authorName agrees", 0x10B981],
            $comment->stance === InvalidationComment::DISPUTE => ["$authorName disputes", 0xEF4444],
            default => ["$authorName commented", 0x9CA3AF],
        };

        $this->send(['embeds' => [array_filter([
            'title' => Str::limit($title, 250),
            'color' => $color,
            'url' => route('games.show', $comment->replay_id),
            'description' => $this->withLink($comment->replay_id, $comment->body ? Str::limit($comment->body, 3500) : null),
            'timestamp' => $comment->created_at?->toIso8601String(),
        ])]], $threadId);
    }

    private function embed(InvalidationRequest $request, string $title): array
    {
        $rows = $request->rows();
        $first = $rows->first();
        $teams = $rows->groupBy('team')->map(fn($team, $n) => [
            'name' => 'Team ' . $n . ($team->first()->winning_team ? ' (won)' : ' (lost)'),
            'value' => $team->map(fn($r) => "{$r->player_name} ({$r->race}) " . ($r->points >= 0 ? '+' : '') . $r->points)->join("\n") ?: '—',
            'inline' => true,
        ])->values()->all();

        return [
            'title' => $title,
            'url' => route('games.show', $request->replay_id),
            'color' => 0xF59E0B,
            'description' => $this->withLink($request->replay_id, Str::limit($request->justification, 3500)),
            'fields' => array_merge([
                ['name' => 'Requested by', 'value' => ($request->playerName?->name ?? $request->user->player_name), 'inline' => true],
                ['name' => 'Reason', 'value' => $request->reasonLabel(), 'inline' => true],
                ['name' => 'Game', 'value' => sprintf('`%s` · Season %s · %s · played %s',
                    $request->replay_id, $first?->season_id, $first?->format,
                    $first ? Carbon::parse($first->start_time ?? $first->created_at)->format('Y-m-d H:i') : '?'), 'inline' => false],
            ], $teams),
            'timestamp' => $request->created_at?->toIso8601String(),
        ];
    }

    /**
     * $text followed by a link to the game page, where the players and admins
     * read and reply to the whole conversation (they need to be logged in).
     */
    private function withLink(string $replayId, ?string $text): string
    {
        $link = '[Read the conversation on the site](' . route('games.show', $replayId) . ')';

        return $text ? $text . "\n\n" . $link : $link;
    }

    /**
     * POST to the forum webhook (into $threadId when given). Returns the
     * created message, or null on failure.
     */
    private function send(array $payload, ?string $threadId = null): ?array
    {
        $url = config('services.discord.tickets_webhook');
        if (!$url) {
            Log::warning('discord tickets: DISCORD_TICKETS_WEBHOOK_URL not set, ticket not posted');
            return null;
        }

        // Player-written text must never ping @everyone / roles / users.
        $payload['allowed_mentions'] ??= ['parse' => []];

        try {
            $response = Http::timeout(5)->post($url . '?' . http_build_query(array_filter(['wait' => 'true', 'thread_id' => $threadId])), $payload);
            if ($response->successful()) {
                return $response->json();
            }
            Log::error('discord tickets: post failed', ['status' => $response->status(), 'body' => Str::limit($response->body(), 500)]);
        } catch (\Throwable $e) {
            Log::error('discord tickets: post failed', ['error' => $e->getMessage()]);
        }

        return null;
    }
}
