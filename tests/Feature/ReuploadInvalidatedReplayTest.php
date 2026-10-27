<?php

namespace Tests\Feature;

use App\Http\Controllers\ReplayController;
use App\Models\PlayerName;
use App\Models\Replay;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A game an admin invalidated (e.g. a name wasn't registered yet) has to be
 * uploadable again once it's fixed, without losing the invalid rows.
 */
class ReuploadInvalidatedReplayTest extends TestCase
{
    use RefreshDatabase;

    private const FINGERPRINT = 'same-game';

    private Season $season;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->season = Season::create(['is_active' => 1]);
        $this->admin = User::factory()->admin()->create(['player_name' => 'TheAdmin']);
        foreach (['A', 'B', 'C', 'D'] as $name) {
            User::factory()->create(['player_name' => $name])->primaryPlayerName()->ensureStats($this->season);
        }
    }

    /**
     * Same shape as MultipleUsernamesTest::storeReplay, but with a fixed
     * fingerprint so both uploads are the same game.
     */
    private function storeReplay(string $file): string
    {
        $player = fn($name, $team, $won) => [
            'Name' => $name, 'Team' => $team, 'IsWinner' => $won, 'Race' => 'Zerg',
            'StartTime' => now()->toIso8601String(), 'APM' => 100, 'EAPM' => 80,
        ];

        app(ReplayController::class)->store(
            [
                'Team1' => [$player('A', 1, true), $player('B', 1, true)],
                'Team2' => [$player('C', 2, false), $player('D', 2, false)],
            ],
            storage_path('app/public/uploads/season_' . $this->season->id . '/' . $file),
            self::FINGERPRINT,
            $file,
            ['Header' => ['Frames' => 24 * 300]],
        );

        return Replay::latest('id')->value('replay_id');
    }

    private function elo(string $name): int
    {
        return PlayerName::where('name', $name)->first()
            ->stats()->where('season_id', $this->season->id)->where('format', '2v2')->value('elo');
    }

    public function test_a_valid_game_is_still_a_duplicate(): void
    {
        $this->storeReplay('1.rep');

        $this->assertTrue(Replay::alreadyUploaded(self::FINGERPRINT));
        $this->assertFalse(Replay::alreadyUploaded('another-game'));
    }

    public function test_an_invalidated_game_can_be_uploaded_again_and_is_rated(): void
    {
        $old = $this->storeReplay('1.rep');
        $this->actingAs($this->admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.replays.invalidate', $old));
        $this->assertSame(1000, $this->elo('A'));

        $this->assertFalse(Replay::alreadyUploaded(self::FINGERPRINT));

        $new = $this->storeReplay('2.rep');

        $this->assertNotSame($old, $new);
        $this->assertSame(1100, $this->elo('A'));
        $this->assertSame(950, $this->elo('C'));
        // The invalidated rows are kept as history.
        $this->assertSame(4, Replay::where('replay_id', $old)->where('is_invalid', true)->where('points', 0)->count());
        $this->assertSame(4, Replay::where('replay_id', $new)->where('is_invalid', false)->count());
        // And the game is a duplicate again now that it has valid rows.
        $this->assertTrue(Replay::alreadyUploaded(self::FINGERPRINT));
    }

    public function test_each_copy_downloads_its_own_file(): void
    {
        $dir = storage_path('app/public/uploads/season_' . $this->season->id);
        // Remember which directories this test makes so it can remove them.
        $created = array_values(array_filter([dirname($dir), $dir], fn($d) => !is_dir($d)));
        if ($created) {
            mkdir($dir, 0775, true);
        }
        $files = ["$dir/reupload-test-1.rep", "$dir/reupload-test-2.rep"];
        file_put_contents($files[0], 'old copy');
        file_put_contents($files[1], 'new copy');

        try {
            $old = $this->storeReplay('reupload-test-1.rep');
            $this->actingAs($this->admin)
                ->withSession(['auth.password_confirmed_at' => time()])
                ->post(route('admin.replays.invalidate', $old));
            $new = $this->storeReplay('reupload-test-2.rep');

            $this->assertSame($files[0], $this->get(route('replay.download', $old))->assertOk()->getFile()->getPathname());
            $this->assertSame($files[1], $this->get(route('replay.download', $new))->assertOk()->getFile()->getPathname());
        } finally {
            array_map('unlink', $files);
            array_map('rmdir', array_reverse($created));
        }
    }
}
