<?php

namespace App\Http\Controllers;

use App\Models\PlayerName;
use App\Models\Replay;
use App\Models\Season;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use ZipStream\CompressionMethod;
use ZipStream\ZipStream;

/**
 * Downloads every valid game of a season (optionally one format, optionally
 * one player's games) as a single zip, streamed so nothing is written to disk.
 */
class ReplayZipController extends Controller
{
    public function download(Request $request): Response
    {
        $validated = $request->validate([
            'season' => ['required', 'integer', 'exists:seasons,id'],
            'format' => ['nullable', 'in:2v2,3v3'],
            'player' => ['nullable', 'string', 'max:255'],
        ]);
        $season = Season::findOrFail($validated['season']);
        $format = $validated['format'] ?? null;
        $playerName = isset($validated['player'])
            ? PlayerName::where('name', $validated['player'])->firstOrFail()
            : null;

        $gameIds = Replay::valid()
            ->where('season_id', $season->id)
            ->when($format, fn($q) => $q->where('format', $format))
            ->when($playerName, fn($q) => $q->where('player_name_id', $playerName->id))
            ->distinct()
            ->pluck('replay_id');

        $games = Replay::whereIn('replay_id', $gameIds)
            ->orderBy('created_at')
            ->orderBy('team')
            ->get()
            ->groupBy('replay_id');

        $files = $this->files($games);
        if ($files->isEmpty()) {
            return back()->with('error', 'No replays to download for that selection.');
        }

        $zipName = collect(['SFL', 'season-' . $season->id, $format, $playerName ? $this->safe($playerName->name) : null])
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
     * Names read like 2025-09-16_0414_2v2_A+B_vs_C+D.rep.
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
            $base = Carbon::parse($first->start_time ?? $first->created_at)->format('Y-m-d_Hi')
                . '_' . $first->format . '_' . $teams->implode('_vs_');

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
