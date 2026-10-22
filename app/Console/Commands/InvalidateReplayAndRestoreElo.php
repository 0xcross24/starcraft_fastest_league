<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Replay;
use App\Services\ReplayInvalidator;

class InvalidateReplayAndRestoreElo extends Command
{
    protected $signature = 'replay:invalidate {replay_id}';
    protected $description = 'Invalidate a replay and restore ELO/points for all involved players.';

    public function handle()
    {
        $replayId = $this->argument('replay_id');
        // Accept either full UUID or 8-char prefix
        $isShort = strlen($replayId) === 8;
        $query = $isShort ? ['like', $replayId . '%'] : ['=', $replayId];
        $replays = Replay::where('replay_id', $query[0], $query[1])->get();
        if ($replays->isEmpty()) {
            $this->error('No replay found with that replay_id.');
            return 1;
        }
        if ($replays->every(fn($r) => $r->is_invalid)) {
            $this->warn('Replay(s) already invalidated.');
            return 0;
        }
        if ($isShort) {
            $this->info('Multiple replays may match this short ID. The following will be invalidated:');
            foreach ($replays as $replay) {
                $this->line("Replay ID: {$replay->replay_id} | Player: {$replay->player_name} | Points: {$replay->points} | Created: {$replay->created_at}");
            }
            if (!$this->confirm('Continue and invalidate all listed replays?')) {
                $this->info('Aborted.');
                return 0;
            }
        }

        app(ReplayInvalidator::class)->invalidate($replays);
        $this->info('Replay invalidated and ELO/points restored for all players.');
        return 0;
    }
}
