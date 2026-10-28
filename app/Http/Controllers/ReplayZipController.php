<?php

namespace App\Http\Controllers;

use App\Models\PlayerName;
use App\Models\Replay;
use App\Models\Season;
use App\Support\GameFilter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use ZipStream\CompressionMethod;
use ZipStream\ZipStream;

/**
 * Downloads every valid game of a season (optionally one format, one player's
 * games, a date range, and/or hand-picked games) as a single zip, streamed so
 * nothing is written to disk.
 */
class ReplayZipController extends Controller
{
    public const MAX_PICKED = 100;

    public function download(Request $request): Response
    {
        $validated = $request->validate([
            // Hand-picked games can come from several seasons (admin portal)
            'season' => ['required_without:games', 'nullable', 'integer', 'exists:seasons,id'],
            'format' => ['nullable', 'in:2v2,3v3'],
            'player' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'race' => ['nullable', 'in:' . implode(',', GameFilter::RACES)],
            // Games ticked on the replay list / player page (x-zip-selected-download)
            'games' => ['nullable', 'array', 'max:' . self::MAX_PICKED],
            'games.*' => ['string', 'max:64'],
        ]);
        $picked = $validated['games'] ?? null;
        $season = isset($validated['season']) ? Season::findOrFail($validated['season']) : null;
        $format = $validated['format'] ?? null;
        $playerName = isset($validated['player'])
            ? PlayerName::where('name', $validated['player'])->firstOrFail()
            : null;
        // Same date / race rules as the replay list and player pages; the player
        // is applied here instead, so an unknown name 404s.
        $filter = GameFilter::fromRequest($request, withPlayer: false);
        [$from, $to, $race] = [$filter->from, $filter->to, $filter->race];

        $gameIds = $filter->apply(Replay::valid())
            ->when($season, fn($q) => $q->where('season_id', $season->id))
            ->when($format, fn($q) => $q->where('format', $format))
            ->when($playerName, fn($q) => $q->where('player_name_id', $playerName->id))
            ->when($picked, fn($q) => $q->whereIn('replay_id', $picked))
            ->distinct()
            ->pluck('replay_id');

        $games = Replay::whereIn('replay_id', $gameIds)
            ->orderByRaw(GameFilter::PLAYED_AT)
            ->orderBy('created_at')
            ->orderBy('team')
            ->get()
            ->groupBy('replay_id');

        $files = $this->files($games);
        if ($files->isEmpty()) {
            return back()->with('error', 'No replays to download for that selection.');
        }

        // Named after what's in it: s2_2v2.zip, Wk`Cross-_s2_3v3.zip,
        // Wk`Cross-_s2.zip (all formats), s2.zip (whole season), plus the
        // dates if picked: s2_2v2_2026-10-01_to_2026-10-15.zip, s2_from_2026-10-01.zip,
        // and hand-picked games: s2_2v2_selected.zip (selected.zip across seasons).
        $dates = match (true) {
            $from && $to => $from === $to ? $from : "{$from}_to_{$to}",
            (bool) $from => "from_{$from}",
            (bool) $to => "to_{$to}",
            default => null,
        };
        $zipName = collect([$playerName ? $this->safe($playerName->name) : null, $season ? 's' . $season->id : null, $format, $race, $dates, $picked ? 'selected' : null])
            ->filter()->implode('_') . '.zip';

        return response()->streamDownload(function () use ($files) {
            // Replays are already compressed; storing them is as small and much faster.
            $zip = new ZipStream(
                defaultCompressionMethod: CompressionMethod::STORE,
                sendHttpHeaders: false,
            );
            foreach ($files as $name => $path) {
                $zip->addFileFromPath($name, $path);
            }
            $zip->finish();
        }, $zipName, ['Content-Type' => 'application/zip']);
    }

    /**
     * Archive name => path on disk, skipping games whose file is missing.
     * Names read like 2025-09-16_A+B_vs_C+D.rep; a second game with the same
     * teams that day becomes ..._2.rep (games are in the order played).
     */
    private function files(Collection $games): Collection
    {
        $files = collect();

        foreach ($games as $rows) {
            $first = $rows->first();
            $path = storage_path('app/public/uploads/season_' . $first->season_id . '/' . basename($first->replay_file));
            if (! is_file($path)) {
                continue;
            }

            $teams = $rows->groupBy('team')->map(
                fn($team) => $team->pluck('player_name')->map(fn($n) => $this->safe($n))->implode('+')
            );
            $base = Carbon::parse($first->start_time ?? $first->created_at)->format('Y-m-d')
                . '_' . $teams->implode('_vs_');

            $name = Str::limit($base, 180, '') . '.rep';
            for ($i = 2; $files->has($name); $i++) {
                $name = Str::limit($base, 180, '') . "_{$i}.rep";
            }
            $files[$name] = $path;
        }

        return $files;
    }

    /**
     * Player names can contain characters Windows or macOS won't accept in a
     * file name. Trailing dots and spaces are also invalid on Windows; dashes
     * (as in Wk`Cross-) are fine and kept.
     */
    private function safe(string $name): string
    {
        return trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/', '-', $name), ' .') ?: 'player';
    }
}
