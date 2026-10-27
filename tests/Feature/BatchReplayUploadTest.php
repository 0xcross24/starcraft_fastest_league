<?php

namespace Tests\Feature;

use App\Models\Replay;
use App\Models\Season;
use App\Models\User;
use App\Services\ReplayParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Batch upload. The screp binary needs a real replay under 48 hours old, so
 * the parser is swapped for one that reads the screp JSON straight from the
 * uploaded file; every check after parsing is the real code.
 */
class BatchReplayUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $uploader;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $season = Season::create(['is_active' => 1]);
        foreach (['A', 'B', 'C', 'D'] as $name) {
            User::factory()->create(['player_name' => $name])->primaryPlayerName()->ensureStats($season);
        }
        $this->uploader = User::factory()->create();

        $this->app->instance(ReplayParser::class, new class extends ReplayParser {
            public function parse(string $path): array
            {
                return ['data' => json_decode(file_get_contents($path), true)];
            }
        });
    }

    /** A 2v2 replay file as screp would describe it; team 1 = A+B wins unless $winner = 2. */
    private function replay(string $file, string $hoursAgo, int $winner = 1, string $map = 'OP SFL-', string $host = 'host'): UploadedFile
    {
        $players = [];
        foreach ([['A', 1], ['B', 1], ['C', 2], ['D', 2]] as $i => [$name, $team]) {
            $players[] = ['ID' => $i, 'Name' => $name, 'Team' => $team, 'Race' => ['Name' => 'Zerg']];
        }
        $json = [
            'Header' => [
                'StartTime' => now()->subHours((float) $hoursAgo)->toIso8601String(),
                'Map' => $map, 'Host' => $host, 'Frames' => 24 * 300, 'Players' => $players,
            ],
            'Computed' => [
                'WinnerTeam' => $winner,
                'PlayerDescs' => array_map(fn($p) => ['PlayerID' => $p['ID'], 'APM' => 100, 'EAPM' => 80], $players),
            ],
        ];

        return UploadedFile::fake()->createWithContent($file, json_encode($json));
    }

    private function upload(array $files)
    {
        return $this->actingAs($this->uploader)->post(route('upload.upload'), ['files' => $files]);
    }

    public function test_games_are_rated_oldest_first_whatever_order_they_were_picked(): void
    {
        // Picked newest first. Rated in order: A+B win at 1000 (+100), then C+D
        // win from 950 against 1100 (A loses 50), then A+B win again (+100).
        // Everyone stays in the D bracket, so every game is D vs D.
        $this->upload([
            $this->replay('third.rep', 1, 1, host: 'h3'),
            $this->replay('second.rep', 2, 2, host: 'h2'),
            $this->replay('first.rep', 3, 1, host: 'h1'),
        ])->assertRedirect(route('upload.index'))->assertSessionHas('summary', '3 of 3 replays uploaded.');

        $this->assertSame(
            ['first.rep', 'second.rep', 'third.rep'],
            array_column(session('results'), 'file'),
        );
        // Points come out the same as uploading them one by one in play order
        $rounded = fn($column) => Replay::where('player_name', 'A')->orderBy('id')->pluck($column)->map(fn($v) => (int) round($v))->all();
        $this->assertSame([100, -50, 100], $rounded('points'));
        $this->assertSame([1100, 1050, 1150], $rounded('elo_after'));
    }

    public function test_a_bad_file_does_not_stop_the_rest(): void
    {
        $this->upload([
            $this->replay('good.rep', 1, host: 'h1'),
            $this->replay('wrong-map.rep', 2, map: 'Some Other Map', host: 'h2'),
            $this->replay('too-old.rep', 60, host: 'h3'),
        ])->assertSessionHas('summary', '1 of 3 replays uploaded.');

        $results = collect(session('results'))->keyBy('file');
        $this->assertTrue($results['good.rep']['ok']);
        $this->assertStringStartsWith('Uploaded: A +100', $results['good.rep']['message']);
        $this->assertStringStartsWith('Replay must be played on', $results['wrong-map.rep']['message']);
        $this->assertSame('Replay is too old (over 48 hours).', $results['too-old.rep']['message']);
        $this->assertSame(1, Replay::distinct()->count('replay_id'));
    }

    public function test_the_same_game_twice_in_one_batch_is_only_stored_once(): void
    {
        $this->upload([
            $this->replay('copy-1.rep', 1, host: 'same'),
            $this->replay('copy-2.rep', 1, host: 'same'),
        ])->assertSessionHas('summary', '1 of 2 replays uploaded.');

        $this->assertSame('Replay already exists.', collect(session('results'))->firstWhere('ok', false)['message']);
    }

    public function test_every_stored_file_gets_its_own_name(): void
    {
        $this->upload([
            $this->replay('one.rep', 2, host: 'h1'),
            $this->replay('two.rep', 1, host: 'h2'),
        ]);

        $stored = Replay::pluck('replay_file')->map(fn($p) => basename($p))->unique();
        $this->assertCount(2, $stored);
        foreach ($stored as $name) {
            Storage::disk('public')->assertExists('uploads/season_1/' . $name);
        }
    }

    public function test_at_most_twenty_files_per_upload(): void
    {
        $files = [];
        for ($i = 0; $i < 21; $i++) {
            $files[] = $this->replay("g{$i}.rep", 1, host: "h{$i}");
        }

        $this->upload($files)->assertSessionHasErrors('files');
        $this->assertSame(0, Replay::count());
    }

    public function test_a_single_file_keeps_the_original_messages(): void
    {
        $this->actingAs($this->uploader)->post(route('upload.upload'), ['file' => $this->replay('solo.rep', 1)])
            ->assertSessionHas('success', 'Upload successful!');

        $this->actingAs($this->uploader)->post(route('upload.upload'), ['files' => [$this->replay('solo.rep', 1)]])
            ->assertSessionHas('error', 'Replay already exists.');
    }
}
