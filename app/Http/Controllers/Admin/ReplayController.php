<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Replay;
use App\Models\Season;
use App\Services\ReplayInvalidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ReplayController extends Controller
{
    public function index(Request $request): View
    {
        $seasons = Season::orderByDesc('id')->get();
        $seasonId = $request->input('season');
        $format = $request->input('format');
        $player = trim((string) $request->input('player'));

        // One page entry per game (replay_id), newest first.
        $games = Replay::query()
            ->select('replay_id')
            ->selectRaw('MAX(created_at) as uploaded_at')
            ->when($seasonId, fn($q) => $q->where('season_id', $seasonId))
            ->when($format, fn($q) => $q->where('format', $format))
            ->when($player !== '', fn($q) => $q->whereIn('replay_id', Replay::select('replay_id')
                ->where('player_name', 'like', '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $player) . '%')))
            ->groupBy('replay_id')
            ->orderByDesc('uploaded_at')
            ->paginate(20)
            ->withQueryString();

        $rows = Replay::whereIn('replay_id', $games->pluck('replay_id'))
            ->orderBy('team')
            ->get()
            ->groupBy('replay_id');

        return view('admin.replays.index', compact('games', 'rows', 'seasons', 'seasonId', 'format', 'player'));
    }

    public function invalidate(Request $request, string $replayId, ReplayInvalidator $invalidator): RedirectResponse
    {
        $replays = Replay::where('replay_id', $replayId)->get();
        abort_if($replays->isEmpty(), 404);

        $count = $invalidator->invalidate($replays);

        Log::info('admin: replay.invalidate', [
            'admin_id' => $request->user()->id,
            'admin' => $request->user()->player_name,
            'replay_id' => $replayId,
            'rows' => $count,
        ]);

        return back()->with('status', $count
            ? 'Game ' . substr($replayId, 0, 8) . ' invalidated; its ELO and wins/losses were reversed.'
            : 'That game was already invalid.');
    }
}
