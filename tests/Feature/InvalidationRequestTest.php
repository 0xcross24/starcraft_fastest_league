<?php

namespace Tests\Feature;

use App\Models\InvalidationRequest;
use App\Models\Replay;
use App\Models\Season;
use App\Models\Stats;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InvalidationRequestTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = 'https://discord.com/api/webhooks/1/abc';

    private User $winner;
    private User $loser;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Season::create(['is_active' => 1]);
        config(['services.discord.tickets_webhook' => self::WEBHOOK]);
        Http::fake([self::WEBHOOK . '*' => Http::response(['id' => '900', 'channel_id' => '777'])]);

        $this->winner = User::factory()->create(['player_name' => 'Winner']);
        $this->loser = User::factory()->create(['player_name' => 'Loser']);
        $this->admin = User::factory()->admin()->create(['player_name' => 'TheAdmin']);
        $this->game('game-1');
    }

    private function game(string $replayId): void
    {
        foreach ([[$this->winner, true, 20], [$this->loser, false, -20]] as [$user, $won, $points]) {
            $name = $user->primaryPlayerName();
            Replay::create([
                'replay_id' => $replayId, 'player_name' => $name->name, 'winning_team' => $won ? 1 : 0,
                'replay_file' => 'x', 'team' => $won ? '1' : '2', 'race' => 'Zerg', 'hash' => 'h-' . $replayId,
                'apm' => 1, 'eapm' => 1, 'season_id' => 1, 'format' => '2v2', 'points' => $points,
                'user_id' => $user->id, 'player_name_id' => $name->id,
            ]);
            Stats::updateOrCreate(
                ['player_name_id' => $name->id, 'season_id' => 1, 'format' => '2v2'],
                ['user_id' => $user->id, 'wins' => $won ? 1 : 0, 'losses' => $won ? 0 : 1, 'elo' => 1000 + $points],
            );
        }
    }

    private function ask(User $user, string $replayId = 'game-1', array $data = [])
    {
        return $this->actingAs($user)->from('/replays')->post(route('invalidation-requests.store', $replayId), $data + [
            'replay_id' => $replayId,
            'reason' => 'not_league',
            'justification' => 'This was a practice game, we agreed before it started.',
        ]);
    }

    public function test_a_player_in_the_game_can_request_and_a_discord_forum_post_is_opened(): void
    {
        $this->ask($this->loser)->assertRedirect('/replays')->assertSessionHas('invalidation_request.replay_id', 'game-1');

        $ticket = InvalidationRequest::sole();
        $this->assertSame('open', $ticket->status);
        $this->assertSame('777', $ticket->discord_thread_id);
        $this->assertSame($this->loser->primaryPlayerName()->id, $ticket->player_name_id);

        Http::assertSent(function (Request $request) {
            $data = $request->data();
            return str_starts_with($request->url(), self::WEBHOOK . '?wait=true')
                && !str_contains($request->url(), 'thread_id')
                && str_starts_with($data['thread_name'], 'Game game-1 · S1 2v2 · Loser')
                && $data['allowed_mentions'] === ['parse' => []]
                && str_contains($data['embeds'][0]['description'], 'practice game')
                && collect($data['embeds'][0]['fields'])->contains('value', "Winner (Zerg) +20");
        });
    }

    public function test_players_not_in_the_game_and_guests_cannot_request(): void
    {
        $this->post(route('invalidation-requests.store', 'game-1'), ['reason' => 'other', 'justification' => str_repeat('x', 30)])
            ->assertRedirect(route('login'));
        $this->ask(User::factory()->create())->assertForbidden();
        $this->assertSame(0, InvalidationRequest::count());
        Http::assertNothingSent();
    }

    public function test_a_reason_and_a_real_justification_are_required(): void
    {
        $this->ask($this->loser, data: ['reason' => 'nope', 'justification' => 'too short'])
            ->assertSessionHasErrorsIn('invalidation', ['reason', 'justification']);
        $this->assertSame(0, InvalidationRequest::count());
    }

    public function test_one_request_per_player_per_game_and_a_second_player_replies_in_the_same_ticket(): void
    {
        $this->ask($this->loser);
        $this->ask($this->loser)->assertSessionHas('invalidation_request.message', 'You already asked for this game to be invalidated.');
        $this->assertSame(1, InvalidationRequest::count());

        $this->ask($this->winner);
        $this->assertSame(2, InvalidationRequest::count());
        $this->assertSame('777', InvalidationRequest::where('user_id', $this->winner->id)->value('discord_thread_id'));
        Http::assertSentCount(2);
        Http::assertSent(fn(Request $r) => str_contains($r->url(), 'thread_id=777') && !isset($r->data()['thread_name']));
    }

    public function test_the_request_is_kept_when_discord_is_not_configured(): void
    {
        config(['services.discord.tickets_webhook' => null]);

        $this->ask($this->loser)->assertSessionHas('invalidation_request');
        $this->assertNull(InvalidationRequest::sole()->discord_thread_id);
        Http::assertNothingSent();
    }

    public function test_an_invalid_game_cannot_be_requested(): void
    {
        Replay::query()->update(['is_invalid' => true]);

        $this->ask($this->loser)->assertSessionHas('invalidation_request.message', 'This game is already invalid.');
        $this->assertSame(0, InvalidationRequest::count());
    }

    public function test_the_button_shows_only_to_players_in_the_game(): void
    {
        $this->actingAs($this->loser)->get('/replays?season=1&format=2v2')->assertOk()->assertSee('Request invalidation');
        $this->actingAs(User::factory()->create())->get('/replays?season=1&format=2v2')->assertOk()->assertDontSee('Request invalidation');
        $this->get('/replays?season=1&format=2v2')->assertOk()->assertDontSee('Request invalidation');
        $this->actingAs($this->winner)->get('/player/Winner?season=1&format=2v2')->assertOk()->assertSee('Request invalidation');

        $this->ask($this->loser);
        $this->actingAs($this->loser)->get('/replays?season=1&format=2v2')
            ->assertDontSee('Request invalidation')->assertSee('waiting for an admin');
    }

    public function test_approving_invalidates_the_game_closes_all_its_requests_and_posts_in_the_ticket(): void
    {
        $this->ask($this->loser);
        $this->ask($this->winner);
        $ticket = InvalidationRequest::first();

        $this->withSession(['auth.password_confirmed_at' => time()])->actingAs($this->admin)
            ->get(route('admin.invalidation-requests.index'))->assertOk()->assertSee('practice game')->assertSee('Requests (1)');

        $this->actingAs($this->admin)
            ->post(route('admin.invalidation-requests.approve', $ticket), ['note' => 'Confirmed practice.'])
            ->assertSessionHas('status');

        $this->assertTrue(Replay::where('replay_id', 'game-1')->get()->every->is_invalid);
        $this->assertSame(1000, Stats::where('player_name_id', $this->winner->primaryPlayerName()->id)->value('elo'));
        $this->assertSame(0, InvalidationRequest::open()->count());
        $this->assertSame('Confirmed practice.', $ticket->fresh()->resolution_note);
        $this->assertSame($this->admin->id, $ticket->fresh()->resolved_by);

        Http::assertSent(fn(Request $r) => str_contains($r->url(), 'thread_id=777')
            && ($r->data()['embeds'][0]['title'] ?? '') === 'Approved: game invalidated');
    }

    public function test_rejecting_keeps_the_game_and_the_player_sees_it(): void
    {
        $this->ask($this->loser);

        $this->withSession(['auth.password_confirmed_at' => time()])->actingAs($this->admin)
            ->post(route('admin.invalidation-requests.reject', InvalidationRequest::sole()), ['note' => 'Rated game.']);

        $this->assertFalse(Replay::where('replay_id', 'game-1')->get()->contains->is_invalid);
        $this->assertSame('rejected', InvalidationRequest::sole()->status);
        $this->actingAs($this->loser)->get('/replays?season=1&format=2v2')->assertSee('Invalidation rejected')->assertSee('Rated game.');
        Http::assertSent(fn(Request $r) => ($r->data()['embeds'][0]['title'] ?? '') === 'Rejected: game stays valid');
    }

    public function test_only_admins_can_resolve(): void
    {
        $this->ask($this->loser);

        $this->actingAs($this->winner)->post(route('admin.invalidation-requests.approve', InvalidationRequest::sole()))->assertForbidden();
        $this->assertFalse(Replay::where('replay_id', 'game-1')->get()->contains->is_invalid);
    }
}
