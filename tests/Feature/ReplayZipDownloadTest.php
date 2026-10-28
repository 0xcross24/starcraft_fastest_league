<?php

namespace Tests\Feature;

use App\Models\PlayerName;
use App\Models\Replay;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ReplayZipDownloadTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->season = Season::create(['is_active' => 1]);
        $this->dir = storage_path('app/public/uploads/season_' . $this->season->id);
        File::ensureDirectoryExists($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/ziptest-*.rep') as $file) {
            unlink($file);
        }
        if (is_dir($this->dir) && count(scandir($this->dir)) === 2) {
            rmdir($this->dir);
        }
        parent::tearDown();
    }

    private function makeName(string $name): PlayerName
    {
        return User::factory()->create(['player_name' => $name])->primaryPlayerName();
    }

    /** One game whose .rep exists on disk (unless $withFile is false). */
    private function game(string $id, array $winners, array $losers, string $format = '2v2', string $start = '2026-10-01 20:15:00', bool $withFile = true): void
    {
        $file = "ziptest-{$id}.rep";
        if ($withFile) {
            file_put_contents("{$this->dir}/{$file}", "replay {$id};");
        }
        foreach ([[$winners, 1], [$losers, 2]] as [$team, $teamNo]) {
            foreach ($team as $playerName) {
                Replay::create([
                    'replay_id' => $id, 'player_name' => $playerName->name, 'winning_team' => $teamNo === 1 ? 1 : 0,
                    'start_time' => $start, 'replay_file' => "/var/www/html/storage/app/public/uploads/season_1/{$file}",
                    'team' => (string) $teamNo, 'race' => 'Zerg', 'hash' => "h-$id", 'apm' => 1, 'eapm' => 1,
                    'season_id' => $this->season->id, 'format' => $format, 'points' => 0,
                    'user_id' => $playerName->user_id, 'player_name_id' => $playerName->id,
                ]);
            }
        }
    }

    /** Archive entry names => contents, read from the local file headers (stored, no compression). */
    private function entries(string $zip): array
    {
        $entries = [];
        $offset = 0;
        while (($pos = strpos($zip, "PK\x03\x04", $offset)) !== false) {
            $nameLength = unpack('v', substr($zip, $pos + 26, 2))[1];
            $extraLength = unpack('v', substr($zip, $pos + 28, 2))[1];
            $name = substr($zip, $pos + 30, $nameLength);
            $dataStart = $pos + 30 + $nameLength + $extraLength;
            // Contents are tiny fixed strings in these tests: "replay <id>;"
            preg_match('/^replay ([^;]+);/', substr($zip, $dataStart, 64), $m);
            $entries[$name] = isset($m[1]) ? 'replay ' . $m[1] : '';
            $offset = $dataStart;
        }

        return $entries;
    }

    private function download(array $query)
    {
        return $this->actingAs(User::factory()->create())->get(route('replays.download-zip', $query));
    }

    public function test_guests_must_log_in(): void
    {
        $this->get(route('replays.download-zip', ['season' => 1]))->assertRedirect(route('login'));
    }

    public function test_the_zip_holds_each_valid_game_under_a_readable_name(): void
    {
        [$a, $b, $c, $d] = array_map(fn($n) => $this->makeName($n), ['Wk`Cross-', 'Clan_[SFL]', 'Odd:Name', 'D']);
        $this->game('g1', [$a, $b], [$c, $d]);
        $this->game('g2', [$c, $d], [$a, $b], '2v2', '2026-10-02 09:05:00');
        $this->game('voided', [$a, $b], [$c, $d], '2v2', '2026-10-03 10:00:00');
        Replay::where('replay_id', 'voided')->update(['is_invalid' => true]);

        $response = $this->download(['season' => 1, 'format' => '2v2'])->assertOk();

        $this->assertStringContainsString('filename=s1_2v2.zip', $response->headers->get('Content-Disposition'));
        $this->assertSame([
            '2026-10-01_Wk`Cross-+Clan_[SFL]_vs_Odd-Name+D.rep' => 'replay g1',
            '2026-10-02_Odd-Name+D_vs_Wk`Cross-+Clan_[SFL].rep' => 'replay g2',
        ], $this->entries($response->streamedContent()));
    }

    public function test_a_rematch_on_the_same_day_gets_a_number(): void
    {
        [$a, $b, $c, $d] = array_map(fn($n) => $this->makeName($n), ['A', 'B', 'C', 'D']);
        $this->game('first', [$a, $b], [$c, $d], '2v2', '2026-10-01 20:00:00');
        $this->game('second', [$a, $b], [$c, $d], '2v2', '2026-10-01 21:00:00');

        $this->assertSame([
            '2026-10-01_A+B_vs_C+D.rep' => 'replay first',
            '2026-10-01_A+B_vs_C+D_2.rep' => 'replay second',
        ], $this->entries($this->download(['season' => 1])->streamedContent()));
    }

    public function test_format_and_player_filters(): void
    {
        [$a, $b, $c, $d, $e] = array_map(fn($n) => $this->makeName($n), ['A', 'B', 'C', 'D', 'E']);
        $this->game('two', [$a, $b], [$c, $d]);
        $this->game('three', [$a, $b, $c], [$d, $e, $this->makeName('F')], '3v3');
        $this->game('without-a', [$b, $c], [$d, $e], '2v2', '2026-10-05 12:00:00');

        $both = $this->entries($this->download(['season' => 1])->streamedContent());
        $this->assertSame(['replay two', 'replay three', 'replay without-a'], array_values($both));

        $onlyAResponse = $this->download(['season' => 1, 'player' => 'A']);
        $this->assertSame(['replay two', 'replay three'], array_values($this->entries($onlyAResponse->streamedContent())));
        $this->assertStringContainsString('filename=A_s1.zip', $onlyAResponse->headers->get('Content-Disposition'));
        $this->assertStringContainsString('filename=s1.zip', $this->download(['season' => 1])->headers->get('Content-Disposition'));

        $aIn2v2Response = $this->download(['season' => 1, 'format' => '2v2', 'player' => 'A']);
        $this->assertSame(['replay two'], array_values($this->entries($aIn2v2Response->streamedContent())));
        $this->assertStringContainsString('filename=A_s1_2v2.zip', $aIn2v2Response->headers->get('Content-Disposition'));
    }

    public function test_date_range_filter_is_inclusive_and_named(): void
    {
        [$a, $b, $c, $d] = array_map(fn($n) => $this->makeName($n), ['A', 'B', 'C', 'D']);
        $this->game('sep30', [$a, $b], [$c, $d], '2v2', '2026-09-30 23:59:59');
        $this->game('oct01', [$a, $b], [$c, $d], '2v2', '2026-10-01 00:00:00');
        $this->game('oct05', [$a, $b], [$c, $d], '2v2', '2026-10-05 23:59:59');
        $this->game('oct06', [$a, $b], [$c, $d], '2v2', '2026-10-06 00:00:00');
        $this->game('oct03-3v3', [$a, $b, $c], [$d, $this->makeName('E'), $this->makeName('F')], '3v3', '2026-10-03 12:00:00');

        $range = $this->download(['season' => 1, 'format' => '2v2', 'from' => '2026-10-01', 'to' => '2026-10-05']);
        $this->assertSame(['replay oct01', 'replay oct05'], array_values($this->entries($range->streamedContent())));
        $this->assertStringContainsString('filename=s1_2v2_2026-10-01_to_2026-10-05.zip', $range->headers->get('Content-Disposition'));

        // Reversed dates are swapped rather than giving nothing
        $reversed = $this->download(['season' => 1, 'format' => '2v2', 'from' => '2026-10-05', 'to' => '2026-10-01']);
        $this->assertSame(['replay oct01', 'replay oct05'], array_values($this->entries($reversed->streamedContent())));

        $from = $this->download(['season' => 1, 'player' => 'A', 'from' => '2026-10-03']);
        $this->assertSame(['replay oct03-3v3', 'replay oct05', 'replay oct06'], array_values($this->entries($from->streamedContent())));
        $this->assertStringContainsString('filename=A_s1_from_2026-10-03.zip', $from->headers->get('Content-Disposition'));

        $to = $this->download(['season' => 1, 'to' => '2026-09-30']);
        $this->assertSame(['replay sep30'], array_values($this->entries($to->streamedContent())));
        $this->assertStringContainsString('filename=s1_to_2026-09-30.zip', $to->headers->get('Content-Disposition'));

        $oneDay = $this->download(['season' => 1, 'from' => '2026-10-06', 'to' => '2026-10-06']);
        $this->assertSame(['replay oct06'], array_values($this->entries($oneDay->streamedContent())));
        $this->assertStringContainsString('filename=s1_2026-10-06.zip', $oneDay->headers->get('Content-Disposition'));

        $this->download(['season' => 1, 'from' => '2026-11-01'])->assertRedirect()->assertSessionHas('error');
        $this->download(['season' => 1, 'from' => 'yesterday'])->assertSessionHasErrors('from');
    }

    public function test_date_range_falls_back_to_upload_time_without_a_start_time(): void
    {
        [$a, $b, $c, $d] = array_map(fn($n) => $this->makeName($n), ['A', 'B', 'C', 'D']);
        $this->game('old', [$a, $b], [$c, $d]);
        Replay::where('replay_id', 'old')->update(['start_time' => null, 'created_at' => '2026-09-15 10:00:00']);

        $this->assertSame(['replay old'], array_values($this->entries(
            $this->download(['season' => 1, 'from' => '2026-09-15', 'to' => '2026-09-15'])->streamedContent()
        )));
        $this->download(['season' => 1, 'from' => '2026-10-01'])->assertSessionHas('error');
    }

    public function test_only_the_selected_games(): void
    {
        [$a, $b, $c, $d] = array_map(fn($n) => $this->makeName($n), ['A', 'B', 'C', 'D']);
        $this->game('g1', [$a, $b], [$c, $d], '2v2', '2026-10-01 10:00:00');
        $this->game('g2', [$a, $b], [$c, $d], '2v2', '2026-10-02 10:00:00');
        $this->game('g3', [$a, $b], [$c, $d], '2v2', '2026-10-03 10:00:00');
        $this->game('voided', [$a, $b], [$c, $d], '2v2', '2026-10-04 10:00:00');
        Replay::where('replay_id', 'voided')->update(['is_invalid' => true]);
        $other = Season::create(['is_active' => 0]);
        Replay::create(Replay::where('replay_id', 'g1')->first()->only(['player_name', 'winning_team', 'replay_file', 'team', 'race', 'hash', 'apm', 'eapm', 'format', 'points', 'user_id', 'player_name_id'])
            + ['replay_id' => 'other-season', 'season_id' => $other->id, 'start_time' => '2026-10-01 11:00:00']);

        $response = $this->download(['season' => 1, 'format' => '2v2', 'games' => ['g3', 'g1', 'voided', 'other-season', 'nope']]);
        // Invalid games, other seasons and unknown ids are left out; order is still by play time
        $this->assertSame(['replay g1', 'replay g3'], array_values($this->entries($response->streamedContent())));
        $this->assertStringContainsString('filename=s1_2v2_selected.zip', $response->headers->get('Content-Disposition'));

        $this->assertStringContainsString('filename=A_s1_2v2_selected.zip',
            $this->download(['season' => 1, 'format' => '2v2', 'player' => 'A', 'games' => ['g2']])->headers->get('Content-Disposition'));

        $this->download(['season' => 1, 'games' => ['voided']])->assertRedirect()->assertSessionHas('error');
        $this->download(['season' => 1, 'games' => array_map(fn($i) => "id{$i}", range(1, 101))])->assertSessionHasErrors('games');
    }

    public function test_selected_games_can_span_seasons_when_no_season_is_given(): void
    {
        [$a, $b, $c, $d] = array_map(fn($n) => $this->makeName($n), ['A', 'B', 'C', 'D']);
        $this->game('g1', [$a, $b], [$c, $d], '2v2', '2026-10-01 10:00:00');
        $this->game('g2', [$a, $b], [$c, $d], '2v2', '2026-10-02 10:00:00');
        $other = Season::create(['is_active' => 0]);
        $otherDir = storage_path('app/public/uploads/season_' . $other->id);
        File::ensureDirectoryExists($otherDir);
        file_put_contents("{$otherDir}/ziptest-old.rep", 'replay old;');
        Replay::create(Replay::where('replay_id', 'g1')->first()->only(['player_name', 'winning_team', 'team', 'race', 'hash', 'apm', 'eapm', 'format', 'points', 'user_id', 'player_name_id'])
            + ['replay_id' => 'old', 'season_id' => $other->id, 'start_time' => '2026-09-01 11:00:00', 'replay_file' => 'ziptest-old.rep']);

        try {
            $response = $this->download(['games' => ['g2', 'old']]);
            $this->assertSame(['replay old', 'replay g2'], array_values($this->entries($response->streamedContent())));
            $this->assertStringContainsString('filename=selected.zip', $response->headers->get('Content-Disposition'));
        } finally {
            File::deleteDirectory($otherDir);
        }

        // Without picks a season is still required
        $this->download([])->assertSessionHasErrors('season');
    }

    public function test_admin_replays_page_has_select_boxes_for_valid_games(): void
    {
        [$a, $b, $c, $d] = array_map(fn($n) => $this->makeName($n), ['A', 'B', 'C', 'D']);
        $this->game('g1', [$a, $b], [$c, $d]);
        $this->game('voided', [$a, $b], [$c, $d]);
        Replay::where('replay_id', 'voided')->update(['is_invalid' => true]);

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->actingAs(User::factory()->admin()->create())->get('/admin/replays')
            ->assertOk()
            ->assertSee('All formats')->assertDontSee('2v2 + 3v3')
            ->assertSee('class="js-zip-pick rounded border-gray-400 bg-gray-900 text-blue-600" value="g1"', false)
            ->assertDontSee('value="voided"', false)
            ->assertSee('Select all on page')
            ->assertSee('data-base="' . e(route('replays.download-zip')) . '"', false);
    }

    public function test_race_filter_matches_the_replay_list(): void
    {
        [$a, $b, $c, $d] = array_map(fn($n) => $this->makeName($n), ['A', 'B', 'C', 'D']);
        $this->game('zerg-game', [$a, $b], [$c, $d]);
        $this->game('toss-game', [$a, $b], [$c, $d], '2v2', '2026-10-02 10:00:00');
        Replay::where('replay_id', 'toss-game')->update(['race' => 'Protoss']);
        Replay::where('replay_id', 'zerg-game')->where('player_name', 'C')->update(['race' => 'Terran']);

        $toss = $this->download(['season' => 1, 'race' => 'Protoss']);
        $this->assertSame(['replay toss-game'], array_values($this->entries($toss->streamedContent())));
        $this->assertStringContainsString('filename=s1_Protoss.zip', $toss->headers->get('Content-Disposition'));
        // With a player: the race that player played
        $this->assertSame(['replay zerg-game'], array_values($this->entries($this->download(['season' => 1, 'player' => 'C', 'race' => 'Terran'])->streamedContent())));
        $this->download(['season' => 1, 'player' => 'A', 'race' => 'Terran'])->assertSessionHas('error');
        $this->download(['season' => 1, 'race' => 'Robot'])->assertSessionHasErrors('race');
    }

    public function test_games_whose_file_is_missing_are_skipped_and_an_empty_result_goes_back(): void
    {
        [$a, $b, $c, $d] = array_map(fn($n) => $this->makeName($n), ['A', 'B', 'C', 'D']);
        $this->game('on-disk', [$a, $b], [$c, $d]);
        $this->game('gone', [$a, $b], [$c, $d], '2v2', '2026-10-02 00:00:00', withFile: false);

        $this->assertSame(['replay on-disk'], array_values($this->entries($this->download(['season' => 1])->streamedContent())));

        $this->download(['season' => 1, 'format' => '3v3'])->assertRedirect()->assertSessionHas('error');
    }

    public function test_downloads_are_rate_limited(): void
    {
        [$a, $b, $c, $d] = array_map(fn($n) => $this->makeName($n), ['A', 'B', 'C', 'D']);
        $this->game('g1', [$a, $b], [$c, $d]);
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->get(route('replays.download-zip', ['season' => 1]))->assertOk();
        }
        $this->actingAs($user)->get(route('replays.download-zip', ['season' => 1]))->assertStatus(429);
    }

    public function test_pages_show_the_buttons_to_everyone(): void
    {
        [$a, $b, $c, $d] = array_map(fn($n) => $this->makeName($n), ['A', 'B', 'C', 'D']);
        $this->game('g1', [$a, $b], [$c, $d]);
        $a->ensureStats($this->season);
        $zipUrl = route('replays.download-zip', ['season' => 1, 'format' => '2v2']);

        // Guests see them too; clicking sends them to log in and back (DownloadAfterLoginTest)
        $this->get('/replays?season=1&format=2v2')->assertOk()->assertSee('Download all (1) .zip')->assertSee('name="from"', false);
        $this->actingAs(User::factory()->create())->get('/replays?season=1&format=2v2')
            ->assertSee('Download all (1) .zip')->assertSee($zipUrl);
        // The profile button covers the whole season, both formats
        $this->game('a-3v3', [$a, $b, $c], [$d, $this->makeName('E'), $this->makeName('F')], '3v3');
        $this->actingAs(User::factory()->create())->get('/player/A?season=1&format=2v2')
            ->assertSee(route('replays.download-zip', ['season' => 1, 'format' => '2v2', 'player' => 'A']))
            ->assertSee('Download 2v2 (1) .zip')
            ->assertSee(route('replays.download-zip', ['season' => 1, 'player' => 'A']))
            ->assertSee('Download all formats (2) .zip')
            ->assertSee('name="from"', false)->assertDontSee('name="player"', false);
        $this->actingAs(User::factory()->create())->get('/replays?season=1&format=2v2')
            ->assertSee('name="from"', false)->assertSee('name="player"', false)->assertSee('name="format" value="2v2"', false);
        // Each game gets a Select box, and the page a "Download selected" zip of the picks
        $this->get('/replays?season=1&format=2v2')
            ->assertSee('class="js-zip-pick rounded border-gray-400 dark:bg-gray-900 text-blue-600" value="g1"', false)
            ->assertSee('data-base="' . e(route('replays.download-zip', ['season' => 1, 'format' => '2v2'])) . '"', false);
        $this->get('/player/A?season=1&format=2v2')
            ->assertSee('value="g1"', false)
            ->assertSee('data-scope="1:2v2:A"', false);
    }
}
