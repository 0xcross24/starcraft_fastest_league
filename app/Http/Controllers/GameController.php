<?php

namespace App\Http\Controllers;

use App\Models\InvalidationRequest;
use App\Models\Replay;
use App\Services\InvalidationCase;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GameController extends Controller
{
    /**
     * One game: its teams, and for the players in it (and admins) the
     * invalidation discussion, if there is one.
     */
    public function show(Request $request, string $replayId, InvalidationCase $case): View
    {
        $rows = Replay::where('replay_id', $replayId)->orderBy('team')->get();
        abort_if($rows->isEmpty(), 404);

        $user = $request->user();
        $isPlayer = $user && $rows->contains('user_id', $user->id);
        $canSee = $isPlayer || $user?->isAdmin();

        $requests = $canSee ? InvalidationRequest::with(['user', 'playerName', 'resolver'])->where('replay_id', $replayId)->orderBy('id')->get() : collect();

        return view('games.show', [
            'replayId' => $replayId,
            'rows' => $rows,
            'isPlayer' => $isPlayer,
            'requests' => $requests,
            'open' => $requests->contains('status', InvalidationRequest::OPEN),
            'stances' => $requests->isNotEmpty() ? $case->stances($replayId) : collect(),
            'players' => $case->playedNames($replayId),
            'comments' => $requests->isNotEmpty() ? $case->comments($replayId) : collect(),
        ]);
    }
}
