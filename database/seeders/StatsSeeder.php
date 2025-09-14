<?php

namespace Database\Seeders;

use App\Models\Stats;
use App\Models\Season;
use App\Models\User;
use Illuminate\Database\Seeder;

class StatsSeeder extends Seeder
{

    /**
     * Run the database seeds.
     */
    public function run()
    {
        $season = Season::where('is_active', true)-> first();

        if (!$season) {
            $this->command->error('No active season found.');
            return;
        }

        $user = User::where('player_name', '[SFL]Cross-')->first();

        if (!$user) {
            $this->command->error('User [SFL]Cross- not found.');
            return;
        }

        // Create specific users with custom data
        Stats::create([
            'wins'      => 25,
            'losses'    => 6,
            'elo'       => 1280,
            'format'    => '2v2',
            'user_id'   => $user->id,
            'season_id' => $season->id,
        ]);

        Stats::create([
            'wins'      => 19,
            'losses'    => 2,
            'elo'       => 1222,
            'format'    => '3v3',
            'user_id'   => $user->id,
            'season_id' => $season->id,
        ]);

        $user = User::where('player_name', '[SFL]Bigwan')->first();

        if (!$user) {
            $this->command->error('User [SFL]Bigwan not found.');
            return;
        }

        // Create specific users with custom data
        Stats::create([
            'wins'      => 14,
            'losses'    => 16,
            'elo'       => 999,
            'format'    => '2v2',
            'user_id'   => $user->id,
            'season_id' => $season->id,
        ]);

        Stats::create([
            'wins'      => 11,
            'losses'    => 11,
            'elo'       => 1103,
            'format'    => '3v3',
            'user_id'   => $user->id,
            'season_id' => $season->id,
        ]);

        $user = User::where('player_name', '[SFL]Dizzy')->first();

        if (!$user) {
            $this->command->error('User [SFL]Dizzy not found.');
            return;
        }

        // Create specific users with custom data
        Stats::create([
            'wins'      => 12,
            'losses'    => 6,
            'elo'       => 1067,
            'format'    => '2v2',
            'user_id'   => $user->id,
            'season_id' => $season->id,
        ]);

        Stats::create([
            'wins'      => 4,
            'losses'    => 6,
            'elo'       => 938,
            'format'    => '3v3',
            'user_id'   => $user->id,
            'season_id' => $season->id,
        ]);

        $user = User::where('player_name', '[SFL]gbomb')->first();

        if (!$user) {
            $this->command->error('User [SFL]gbomb not found.');
            return;
        }

        // Create specific users with custom data
        Stats::create([
            'wins'      => 2,
            'losses'    => 0,
            'elo'       => 1062,
            'format'    => '2v2',
            'user_id'   => $user->id,
            'season_id' => $season->id,
        ]);

        Stats::create([
            'wins'      => 2,
            'losses'    => 1,
            'elo'       => 999,
            'format'    => '3v3',
            'user_id'   => $user->id,
            'season_id' => $season->id,
        ]);
        
        $user = User::where('player_name', '[SFL]nightstand')->first();

        if (!$user) {
            $this->command->error('User [SFL]nightstand not found.');
            return;
        }

        // Create specific users with custom data
        Stats::create([
            'wins'      => 2,
            'losses'    => 2,
            'elo'       => 1038,
            'format'    => '2v2',
            'user_id'   => $user->id,
            'season_id' => $season->id,
        ]);

        Stats::create([
            'wins'      => 2,
            'losses'    => 5,
            'elo'       => 944,
            'format'    => '3v3',
            'user_id'   => $user->id,
            'season_id' => $season->id,
        ]);

        $user = User::where('player_name', 'Bidulgi')->first();

        if (!$user) {
            $this->command->error('User Bidulgi not found.');
            return;
        }

        // Create specific users with custom data
        Stats::create([
            'wins'      => 0,
            'losses'    => 0,
            'elo'       => 1000,
            'format'    => '2v2',
            'user_id'   => $user->id,
            'season_id' => $season->id,
        ]);

        Stats::create([
            'wins'      => 0,
            'losses'    => 0,
            'elo'       => 1000,
            'format'    => '3v3',
            'user_id'   => $user->id,
            'season_id' => $season->id,
        ]);
        
        $user = User::where('player_name', 'BL1ZZ')->first();

        if (!$user) {
            $this->command->error('User BL1ZZ not found.');
            return;
        }

        // Create specific users with custom data
        Stats::create([
            'wins'      => 0,
            'losses'    => 0,
            'elo'       => 1000,
            'format'    => '2v2',
            'user_id'   => $user->id,
            'season_id' => $season->id,
        ]);

        Stats::create([
            'wins'      => 0,
            'losses'    => 0,
            'elo'       => 1000,
            'format'    => '3v3',
            'user_id'   => $user->id,
            'season_id' => $season->id,
        ]);

        $user = User::where('player_name', '[SFL]Shrink')->first();

        if (!$user) {
            $this->command->error('User [SFL]Shrink not found.');
            return;
        }

        // Create specific users with custom data
        Stats::create([
            'wins'      => 0,
            'losses'    => 0,
            'elo'       => 1000,
            'format'    => '2v2',
            'user_id'   => $user->id,
            'season_id' => $season->id,
        ]);

        Stats::create([
            'wins'      => 2,
            'losses'    => 0,
            'elo'       => 1087,
            'format'    => '3v3',
            'user_id'   => $user->id,
            'season_id' => $season->id,
        ]);

        $user = User::where('player_name', 'Vince88')->first();

        if (!$user) {
            $this->command->error('User Vince88 not found.');
            return;
        }

        // Create specific users with custom data
        Stats::create([
            'wins'      => 0,
            'losses'    => 0,
            'elo'       => 1000,
            'format'    => '2v2',
            'user_id'   => $user->id,
            'season_id' => $season->id,
        ]);

        Stats::create([
            'wins'      => 0,
            'losses'    => 0,
            'elo'       => 1000,
            'format'    => '3v3',
            'user_id'   => $user->id,
            'season_id' => $season->id,
        ]);

        $user = User::where('player_name', 'flowcc')->first();

        if (!$user) {
            $this->command->error('User flowcc not found.');
            return;
        }

        // Create specific users with custom data
        Stats::create([
            'wins'      => 1,
            'losses'    => 1,
            'elo'       => 996,
            'format'    => '2v2',
            'user_id'   => $user->id,
            'season_id' => $season->id,
        ]);

        Stats::create([
            'wins'      => 4,
            'losses'    => 1,
            'elo'       => 1103,
            'format'    => '3v3',
            'user_id'   => $user->id,
            'season_id' => $season->id,
        ]);

        $user = User::where('player_name', '[SFL]tb`Hero-')->first();

        if (!$user) {
            $this->command->error('User [SFL]tb`Hero- not found.');
            return;
        }

        // Create specific users with custom data
        Stats::create([
            'wins'      => 2,
            'losses'    => 3,
            'elo'       => 982,
            'format'    => '2v2',
            'user_id'   => $user->id,
            'season_id' => $season->id,
        ]);

        Stats::create([
            'wins'      => 5,
            'losses'    => 10,
            'elo'       => 1006,
            'format'    => '3v3',
            'user_id'   => $user->id,
            'season_id' => $season->id,
        ]);

        $user = User::where('player_name', '[SFL]Banging')->first();

        if (!$user) {
            $this->command->error('User [SFL]Banging not found.');
            return;
        }

        // Create specific users with custom data
        Stats::create([
            'wins'      => 11,
            'losses'    => 18,
            'elo'       => 887,
            'format'    => '2v2',
            'user_id'   => $user->id,
            'season_id' => $season->id,
        ]);

        Stats::create([
            'wins'      => 7,
            'losses'    => 10,
            'elo'       => 955,
            'format'    => '3v3',
            'user_id'   => $user->id,
            'season_id' => $season->id,
        ]);

        $user = User::where('player_name', '[SFL]Health')->first();

        if (!$user) {
            $this->command->error('User [SFL]Health not found.');
            return;
        }

        // Create specific users with custom data
        Stats::create([
            'wins'      => 8,
            'losses'    => 18,
            'elo'       => 814,
            'format'    => '2v2',
            'user_id'   => $user->id,
            'season_id' => $season->id,
        ]);

        Stats::create([
            'wins'      => 5,
            'losses'    => 8,
            'elo'       => 906,
            'format'    => '3v3',
            'user_id'   => $user->id,
            'season_id' => $season->id,
        ]);

        $user = User::where('player_name', '[SFL]fil0')->first();

        if (!$user) {
            $this->command->error('User [SFL]fil0 not found.');
            return;
        }

        // Create specific users with custom data
        Stats::create([
            'wins'      => 3,
            'losses'    => 10,
            'elo'       => 793,
            'format'    => '2v2',
            'user_id'   => $user->id,
            'season_id' => $season->id,
        ]);

        Stats::create([
            'wins'      => 5,
            'losses'    => 12,
            'elo'       => 906,
            'format'    => '3v3',
            'user_id'   => $user->id,
            'season_id' => $season->id,
        ]);
    }
}
