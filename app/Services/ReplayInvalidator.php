<?php

namespace App\Services;

use App\Models\Replay;
use App\Models\Stats;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Marks a game's replay rows invalid and takes back the elo and win/loss each
 * one gave. Shared by the replay:invalidate command and the admin portal.
 */
class ReplayInvalidator
{
    /**
     * @param  Collection<int, Replay>  $replays
     * @return int  rows invalidated (already-invalid rows are skipped)
     */
    public function invalidate(Collection $replays): int
    {
        $replays = $replays->reject(fn(Replay $r) => $r->is_invalid);

        DB::transaction(function () use ($replays) {
            foreach ($replays as $replay) {
                $stats = Stats::where('user_id', $replay->user_id)
                    ->where('season_id', $replay->season_id)
                    ->where('format', $replay->format)
                    ->first();
                if ($stats) {
                    // Reverse ELO/points
                    $stats->elo -= $replay->points;
                    // Reverse win/loss
                    if ($replay->winning_team) {
                        $stats->wins = max(0, $stats->wins - 1);
                    } else {
                        $stats->losses = max(0, $stats->losses - 1);
                    }
                    $stats->save();
                }
                $replay->is_invalid = true;
                $replay->points = 0;
                $replay->elo_after = null;
                $replay->save();
            }
        });

        return $replays->count();
    }
}
