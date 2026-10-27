<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Re-rates a season from its stored games: every name restarts at 1000 with
 * no wins or losses, then each valid game is replayed in the order it was
 * uploaded (replay row id), through the same EloService::rateGame as an
 * upload. Rewrites stats elo/wins/losses and each replay row's points and
 * elo_after. Invalidated games stay at 0 points with no elo_after.
 */
class EloRecalculator
{
    public function __construct(private EloService $elo) {}

    /**
     * @return array{games: int, stats: array<string, array{elo: int, wins: int, losses: int}>}
     *         stats keyed "player_name_id|format"
     */
    public function recalculate(int $seasonId, bool $write = true): array
    {
        $rows = DB::table('replays')
            ->where('season_id', $seasonId)
            ->where('is_invalid', false)
            ->orderBy('id')
            ->get(['id', 'replay_id', 'player_name_id', 'format', 'winning_team']);

        $stats = [];
        $rowUpdates = [];
        // groupBy keeps first-seen order, i.e. each game's first row id
        foreach ($rows->groupBy('replay_id') as $game) {
            $format = $game->first()->format;
            $key = fn($row) => "{$row->player_name_id}|{$format}";
            $eloOf = fn($row) => $stats[$key($row)]['elo'] ?? 1000;

            $winners = $game->where('winning_team', 1)->mapWithKeys(fn($r) => [$r->player_name_id => $eloOf($r)])->all();
            $losers = $game->where('winning_team', '!=', 1)->mapWithKeys(fn($r) => [$r->player_name_id => $eloOf($r)])->all();
            $changes = $this->elo->rateGame($winners, $losers);

            foreach ($game as $row) {
                $current = $stats[$key($row)] ?? ['elo' => 1000, 'wins' => 0, 'losses' => 0];
                $current['elo'] += $changes[$row->player_name_id];
                $current[$row->winning_team == 1 ? 'wins' : 'losses']++;
                $stats[$key($row)] = $current;
                $rowUpdates[$row->id] = ['points' => $changes[$row->player_name_id], 'elo_after' => $current['elo']];
            }
        }

        if ($write) {
            DB::transaction(function () use ($seasonId, $stats, $rowUpdates) {
                DB::table('stats')->where('season_id', $seasonId)->update(['elo' => 1000, 'wins' => 0, 'losses' => 0]);
                foreach ($stats as $key => $values) {
                    [$playerNameId, $format] = explode('|', $key, 2);
                    DB::table('stats')
                        ->where(['season_id' => $seasonId, 'player_name_id' => $playerNameId, 'format' => $format])
                        ->update($values);
                }
                foreach ($rowUpdates as $id => $values) {
                    DB::table('replays')->where('id', $id)->update($values);
                }
            });
        }

        return ['games' => $rows->pluck('replay_id')->unique()->count(), 'stats' => $stats];
    }
}
