<?php

namespace Tests\Feature;

use App\Models\InvalidationComment;
use App\Models\InvalidationRequest;
use App\Models\Replay;
use App\Models\Season;
use App\Models\Stats;
use App\Models\User;
use App\Notifications\InvalidationUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The players in a game agree with, dispute or comment on an invalidation
 * request on the site, admins reply there, everything is mirrored to the
 * game's Discord ticket and emailed to the players, and the game is
 * invalidated on its own once every player agrees.
 */
class InvalidationDiscussionTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = 'https://discord.com/api/webhooks/1/abc';

    /** @var array<string, User> */
    private array $p = [];
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Season::create(['is_active' => 1]);
        config(['services.discord.tickets_webhook' => self::WEBHOOK]);
        Http::fake([self::WEBHOOK . '*' => Http::response(['id' => '900', 'channel_id' => '777'])]);
        Notification::fake();

        // 2v2: A + B beat C + D
        foreach (['A' => [1, 50], 'B' => [1, 50], 'C' => [0, -50], 'D' => [0, -50]] as $name => [$won, $points]) {
            $user = $this->p[$name] = User::factory()->create(['player_name' => $name]);
            $user->ensureStats(Season::first());
            $user->stats()->where('format', '2v2')->update(['elo' => 1000 + $points, 'wins' => $won, 'losses' => 1 - $won]);
            Replay::create([
                'replay_id' => 'game-1', 'player_name' => $name, 'winning_team' => $won, 'replay_file' => 'x',
                'team' => $won ? '1' : '2', 'race' => 'Zerg', 'hash' => 'h', 'apm' => 1, 'eapm' => 1,
                'season_id' => 1, 'format' => '2v2', 'points' => $points, 'user_id' => $user->id,
                'player_name_id' => $user->primaryPlayerName()->id,
            ]);
        }
        $this->admin = User::factory()->admin()->create(['player_name' => 'TheAdmin']);
    }

    private function ask(string $name)
    {
        return $this->actingAs($this->p[$name])->post(route('invalidation-requests.store', 'game-1'), [
            'reason' => 'not_league', 'justification' => 'This was a practice game, we agreed before it started.',
        ]);
    }

    private function say(string $name, ?string $stance, ?string $body = null)
    {
        return $this->actingAs($this->p[$name])->post(route('invalidation-requests.comment', 'game-1'), array_filter(compact('stance', 'body')));
    }

    private function invalid(): bool
    {
        return Replay::where('replay_id', 'game-1')->get()->every->is_invalid;
    }

    public function test_the_other_players_are_emailed_and_see_where_it_stands(): void
    {
        $this->ask('C');

        Notification::assertSentTo([$this->p['A'], $this->p['B'], $this->p['D']], InvalidationUpdate::class, fn($n) => $n->event === 'opened');
        Notification::assertNotSentTo($this->p['C'], InvalidationUpdate::class);

        $this->actingAs($this->p['A'])->get('/replays?season=1&format=2v2')->assertOk()
            ->assertSee('1 agree · 0 dispute')->assertSee('Agree or dispute')->assertSee(route('games.show', 'game-1'));
        $this->actingAs($this->p['A'])->get(route('games.show', 'game-1'))->assertOk()
            ->assertSee('practice game')->assertSee('no answer yet')->assertSee('I dispute this');
    }

    public function test_only_players_and_admins_see_the_discussion(): void
    {
        $this->ask('C');
        auth()->logout();

        $this->get(route('games.show', 'game-1'))->assertOk()->assertSee('Team 1')->assertDontSee('practice game');
        $this->actingAs(User::factory()->create())->get(route('games.show', 'game-1'))->assertOk()->assertDontSee('practice game');
        $this->actingAs($this->admin)->get(route('games.show', 'game-1'))->assertOk()
            ->assertSee('practice game')->assertSee('Reply as admin')->assertDontSee('I dispute this');
        $this->get(route('games.show', 'nope'))->assertNotFound();
    }

    public function test_a_dispute_needs_a_reason_is_mirrored_to_discord_and_emailed(): void
    {
        $this->ask('C');

        $this->say('A', 'dispute')->assertSessionHasErrorsIn('discussion', 'body');
        $this->say('A', 'dispute', 'It was a rated game, we said so in the lobby.')->assertRedirect(route('games.show', 'game-1'));

        $this->assertSame('dispute', InvalidationComment::sole()->stance);
        Http::assertSent(fn(Request $r) => str_contains($r->url(), 'thread_id=777')
            && ($r->data()['embeds'][0]['title'] ?? '') === 'A disputes'
            && str_contains($r->data()['embeds'][0]['description'] ?? '', 'rated game'));
        Notification::assertSentTo($this->p['C'], InvalidationUpdate::class, fn($n) => $n->event === 'dispute' && $n->authorName === 'A');
        Notification::assertNotSentTo($this->p['A'], InvalidationUpdate::class, fn($n) => $n->event === 'dispute');
    }

    public function test_the_game_is_invalidated_on_its_own_once_every_player_agrees(): void
    {
        $this->ask('C');
        $this->say('A', 'agree');
        $this->say('B', 'agree');
        $this->assertFalse($this->invalid());

        $this->say('D', 'agree', 'Yes, practice.');

        $this->assertTrue($this->invalid());
        $ticket = InvalidationRequest::sole();
        $this->assertSame('approved', $ticket->status);
        $this->assertNull($ticket->resolved_by);
        $this->assertSame(1000, Stats::where('user_id', $this->p['A']->id)->where('format', '2v2')->value('elo'));
        Http::assertSent(fn(Request $r) => ($r->data()['embeds'][0]['title'] ?? '') === 'Approved: game invalidated'
            && ($r->data()['embeds'][0]['footer']['text'] ?? '') === 'by all players agreed');
        Notification::assertSentTo($this->p['C'], InvalidationUpdate::class, fn($n) => $n->event === 'approved');
    }

    public function test_a_dispute_holds_it_for_an_admin_until_the_player_changes_their_mind(): void
    {
        $this->ask('C');
        $this->say('A', 'agree');
        $this->say('B', 'dispute', 'No, this one counted.');
        $this->say('D', 'agree');
        $this->assertFalse($this->invalid());
        $this->assertSame('open', InvalidationRequest::sole()->status);

        $this->say('B', 'agree', 'Ok, checked the chat, it was practice.');
        $this->assertTrue($this->invalid());
    }

    public function test_admins_reply_on_the_site_and_players_cannot_post_after_a_decision(): void
    {
        $this->ask('C');

        $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.invalidation-requests.reply', 'game-1'), ['body' => 'C, which part of the lobby chat?'])
            ->assertSessionHas('status');

        $reply = InvalidationComment::sole();
        $this->assertTrue($reply->from_admin);
        $this->assertNull($reply->stance);
        Notification::assertSentTo([$this->p['A'], $this->p['C']], InvalidationUpdate::class, fn($n) => $n->event === 'admin_reply');
        Http::assertSent(fn(Request $r) => ($r->data()['embeds'][0]['title'] ?? '') === 'Admin TheAdmin replied');
        $this->actingAs($this->p['C'])->get(route('games.show', 'game-1'))->assertSee('which part of the lobby chat');

        $this->actingAs($this->admin)->post(route('admin.invalidation-requests.reject', InvalidationRequest::sole()), ['note' => 'Rated.']);
        Notification::assertSentTo($this->p['C'], InvalidationUpdate::class, fn($n) => $n->event === 'rejected' && $n->body === 'Rated.');

        $this->say('C', null, 'But...')->assertSessionHas('error');
        $this->assertSame(1, InvalidationComment::count());
    }

    public function test_people_outside_the_game_cannot_post(): void
    {
        $this->ask('C');

        $this->actingAs(User::factory()->create())->post(route('invalidation-requests.comment', 'game-1'), ['stance' => 'agree'])->assertForbidden();
        auth()->logout();
        $this->post(route('invalidation-requests.comment', 'game-1'), ['stance' => 'agree'])->assertRedirect(route('login'));
        $this->assertSame(0, InvalidationComment::count());
    }

    public function test_the_email_links_to_the_game_page(): void
    {
        $mail = (new InvalidationUpdate('game-1', 'dispute', 'A', 'No way.'))->toMail($this->p['C']);

        $this->assertSame('SFL: A disputed invalidating game game-1', $mail->subject);
        $this->assertSame(route('games.show', 'game-1'), $mail->actionUrl);
    }
}
