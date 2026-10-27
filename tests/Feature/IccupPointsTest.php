<?php

namespace Tests\Feature;

use App\Http\Controllers\ReplayController;
use App\Models\PlayerName;
use App\Models\Season;
use App\Models\User;
use App\Services\EloService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ratings use iCCup's points table instead of Elo's expected-score formula.
 * Under Elo nobody could get past D (the best rating in two seasons was 1345),
 * because points shrink towards zero once you're a few hundred above the field.
 */
class IccupPointsTest extends TestCase
{
    use RefreshDatabase;

    /** A rating inside each bracket. */
    private const ELO = ['D' => 1000, 'C' => 2500, 'B' => 4000, 'A-' => 5000, 'A' => 5500, 'A+' => 6000, 'S' => 6500];

    /**
     * Copied in the iCCup chart's own layout, rows = opponent (VS),
     * columns = your rank, each cell [win, loss]. EloService keeps it the
     * other way round, so a transposed table fails here.
     */
    private const CHART = [
        'D' => [[100, 50], [75, 62], [50, 75], [25, 131], [10, 150], [10, 150], [10, 200]],
        'C' => [[125, 37], [100, 50], [75, 62], [50, 112], [25, 131], [10, 150], [10, 200]],
        'B' => [[150, 25], [125, 37], [100, 50], [75, 93], [50, 112], [25, 131], [10, 200]],
        'A-' => [[175, 12], [150, 25], [125, 37], [100, 75], [75, 93], [50, 112], [25, 175]],
        'A' => [[200, 0], [175, 12], [150, 25], [125, 56], [100, 75], [75, 93], [50, 150]],
        'A+' => [[200, 0], [200, 0], [175, 12], [150, 37], [125, 56], [100, 75], [75, 125]],
        'S' => [[200, 0], [200, 0], [200, 0], [175, 25], [150, 37], [125, 56], [100, 100]],
    ];

    public function test_every_cell_matches_the_iccup_chart(): void
    {
        $elo = app(EloService::class);
        $ranks = array_keys(self::ELO);

        foreach (self::CHART as $opponent => $cells) {
            foreach ($cells as $i => [$win, $loss]) {
                $you = $ranks[$i];
                $this->assertSame($win, $elo->points(self::ELO[$you], self::ELO[$opponent], true), "$you beats $opponent");
                $this->assertSame(-$loss, $elo->points(self::ELO[$you], self::ELO[$opponent], false), "$you loses to $opponent");
            }
        }
    }

    public function test_plus_minus_grades_share_a_bracket_and_e_counts_as_d(): void
    {
        $elo = app(EloService::class);

        // D-, D+ and E all play as D; C- and C+ as C
        foreach ([400, 600, 1600] as $d) {
            $this->assertSame(75, $elo->points(2000, $d, true));
        }
        $this->assertSame(75, $elo->points(3400, 1000, true));
        // A-, A and A+ are separate brackets
        $this->assertSame(25, $elo->points(5000, 1000, true));
        $this->assertSame(10, $elo->points(5999, 1000, true));
    }

    public function test_team_games_use_own_rank_against_the_other_teams_average(): void
    {
        $season = Season::create(['is_active' => 1]);
        $names = [];
        foreach (['A' => 5500, 'B' => 1000, 'C' => 4000, 'D' => 4000] as $name => $rating) {
            $names[$name] = User::factory()->create(['player_name' => $name])->primaryPlayerName();
            $names[$name]->ensureStats($season);
            $names[$name]->stats()->where('format', '2v2')->update(['elo' => $rating]);
        }
        $row = fn($n, $team, $won) => ['Name' => $n, 'Team' => $team, 'IsWinner' => $won, 'Race' => 'Zerg',
            'StartTime' => now()->toIso8601String(), 'APM' => 1, 'EAPM' => 1];

        // A (A, 5500) + B (D, 1000) beat C + D (both B, 4000).
        // Winners average 3250 (C), losers 4000 (B).
        app(ReplayController::class)->store(
            ['Team1' => [$row('A', 1, true), $row('B', 1, true)], 'Team2' => [$row('C', 2, false), $row('D', 2, false)]],
            '/tmp/x.rep', 'hash-1', 'x.rep', ['Header' => ['Frames' => 24 * 300]],
        );

        $elo = fn($n) => (int) $names[$n]->stats()->where('format', '2v2')->value('elo');
        $this->assertSame(5550, $elo('A')); // A beats B: +50
        $this->assertSame(1150, $elo('B')); // D beats B: +150
        $this->assertSame(3938, $elo('C')); // B loses to C: -62
        $this->assertSame(3938, $elo('D'));
    }

    public function test_rating_never_goes_below_zero(): void
    {
        $season = Season::create(['is_active' => 1]);
        foreach (['A' => 1000, 'B' => 1000, 'C' => 30, 'D' => 1000] as $name => $rating) {
            $playerName = User::factory()->create(['player_name' => $name])->primaryPlayerName();
            $playerName->ensureStats($season);
            $playerName->stats()->where('format', '2v2')->update(['elo' => $rating]);
        }
        $row = fn($n, $team, $won) => ['Name' => $n, 'Team' => $team, 'IsWinner' => $won, 'Race' => 'Zerg',
            'StartTime' => now()->toIso8601String(), 'APM' => 1, 'EAPM' => 1];

        app(ReplayController::class)->store(
            ['Team1' => [$row('A', 1, true), $row('B', 1, true)], 'Team2' => [$row('C', 2, false), $row('D', 2, false)]],
            '/tmp/x.rep', 'hash-1', 'x.rep', ['Header' => ['Frames' => 24 * 300]],
        );

        $c = PlayerName::where('name', 'C')->first();
        $this->assertSame(0, (int) $c->stats()->where('format', '2v2')->value('elo'));
        // The stored change is what was actually taken, so invalidating gives back 30, not 50
        $this->assertSame(-30, (int) \App\Models\Replay::where('player_name', 'C')->value('points'));
    }
}
