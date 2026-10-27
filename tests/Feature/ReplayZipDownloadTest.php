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
            ->assertSee('name="from"', false)->assertSee('name="player" value="A"', false);
        $this->actingAs(User::factory()->create())->get('/replays?season=1&format=2v2')
            ->assertSee('name="from"', false)->assertSee('name="format" value="2v2"', false);
    }
}
