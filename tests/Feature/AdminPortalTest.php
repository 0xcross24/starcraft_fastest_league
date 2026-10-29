<?php

namespace Tests\Feature;

use App\Models\PlayerName;
use App\Models\Replay;
use App\Models\Season;
use App\Models\Stats;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Season::create(['is_active' => 1]);
        $this->admin = User::factory()->admin()->create(['player_name' => 'TheAdmin']);
        $this->withSession(['auth.password_confirmed_at' => time()]);
    }

    private function replayRow(User $user, string $replayId, bool $won, int $points): Replay
    {
        $name = $user->primaryPlayerName();

        return Replay::create([
            'replay_id' => $replayId, 'player_name' => $name->name, 'winning_team' => $won ? 1 : 0,
            'replay_file' => 'x', 'team' => $won ? '1' : '2', 'race' => 'Zerg', 'hash' => 'h-'.$replayId,
            'apm' => 1, 'eapm' => 1, 'season_id' => 1, 'format' => '2v2', 'points' => $points,
            'user_id' => $user->id, 'player_name_id' => $name->id,
        ]);
    }

    public function test_only_admins_can_open_the_portal(): void
    {
        $this->get('/admin/users')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/admin/users')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/admin/replays')->assertForbidden();

        $this->actingAs($this->admin)->get('/admin/users')->assertOk();
        $this->actingAs($this->admin)->get('/admin/replays')->assertOk();
    }

    public function test_the_portal_asks_an_admin_for_their_password_first(): void
    {
        $this->flushSession();

        $this->actingAs($this->admin)->get('/admin/users')->assertRedirect(route('password.confirm'));
        $this->actingAs($this->admin)->post(route('password.confirm'), ['password' => 'password']);
        $this->actingAs($this->admin)->get('/admin/users')->assertOk();
    }

    public function test_non_admins_cannot_change_anything(): void
    {
        $target = User::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('admin.users.update', $target), ['email' => 'x@example.com', 'role' => 'admin'])
            ->assertForbidden();

        $this->assertSame('user', $target->fresh()->role);
    }

    public function test_users_can_be_searched_by_any_of_their_names_or_email(): void
    {
        $user = User::factory()->create(['player_name' => 'Alpha', 'email' => 'alpha@example.com']);
        PlayerName::addFor($user, 'SmurfyName');
        User::factory()->create(['player_name' => 'Bravo']);

        $this->actingAs($this->admin)->get('/admin/users?q=smurfy')
            ->assertOk()->assertSee('Alpha')->assertDontSee('Bravo');
        $this->actingAs($this->admin)->get('/admin/users?q=alpha@')
            ->assertOk()->assertSee('Alpha')->assertDontSee('Bravo');
    }

    public function test_an_admin_can_change_email_but_not_roles(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.users.update', $user), ['email' => 'new@example.com', 'role' => 'user'])
            ->assertSessionHas('status');
        $this->actingAs($this->admin)
            ->patch(route('admin.users.update', $user), ['email' => 'new@example.com', 'role' => 'admin'])
            ->assertSessionHas('error');
        $this->actingAs($this->admin)
            ->patch(route('admin.users.update', $this->admin), ['email' => $this->admin->email, 'role' => 'owner'])
            ->assertSessionHas('error');

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
        $this->assertSame('user', $user->role);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('admin', $this->admin->fresh()->role);
    }

    public function test_an_owner_can_give_and_take_any_role(): void
    {
        $owner = User::factory()->owner()->create();
        $user = User::factory()->create();

        foreach (['admin', 'owner', 'user'] as $role) {
            $this->actingAs($owner)
                ->patch(route('admin.users.update', $user), ['email' => $user->email, 'role' => $role])
                ->assertSessionHas('status');
            $this->assertSame($role, $user->fresh()->role);
        }
        $this->actingAs($owner)
            ->patch(route('admin.users.update', $this->admin), ['email' => $this->admin->email, 'role' => 'user'])
            ->assertSessionHas('status');
        $this->assertSame('user', $this->admin->fresh()->role);

        $this->actingAs($owner)->get(route('admin.users.edit', $user))->assertOk()->assertSee('<select id="role"', false);
        $this->actingAs($this->admin)->get(route('admin.users.edit', $user))->assertOk()->assertDontSee('<select id="role"', false);
    }

    public function test_an_owner_cannot_change_their_own_role(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->patch(route('admin.users.update', $owner), ['email' => $owner->email, 'role' => 'admin'])
            ->assertSessionHas('error');

        $this->assertSame('owner', $owner->fresh()->role);
    }

    public function test_owners_have_admin_access(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->get('/admin/users')->assertOk();
        $this->actingAs($owner)->get('/admin/replays')->assertOk();
        $this->actingAs($owner)->get(route('builds.create'))->assertOk();
        $this->actingAs($owner)->get('/rankings')->assertSee(route('admin.users.index'));
    }

    public function test_admins_cannot_change_an_owner_account(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create(['email' => 'owner@example.com']);

        $this->actingAs($this->admin)->get(route('admin.users.edit', $owner))
            ->assertOk()->assertSee('Only an owner can change it.');
        $this->actingAs($this->admin)
            ->patch(route('admin.users.update', $owner), ['email' => 'evil@example.com', 'role' => 'owner'])
            ->assertForbidden();
        $this->actingAs($this->admin)->post(route('admin.users.password-reset', $owner))->assertForbidden();
        $this->actingAs($this->admin)->post(route('admin.users.names.store', $owner), ['name' => 'Extra'])->assertForbidden();
        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $owner))->assertForbidden();

        $this->assertSame('owner@example.com', $owner->fresh()->email);
        Notification::assertNothingSent();
    }

    public function test_an_admin_can_add_and_remove_a_users_names_within_the_rules(): void
    {
        $user = User::factory()->create(['player_name' => 'Main']);

        $this->actingAs($this->admin)->post(route('admin.users.names.store', $user), ['name' => 'Second']);
        $this->actingAs($this->admin)->post(route('admin.users.names.store', $user), ['name' => 'theadmin'])
            ->assertSessionHasErrorsIn('playerName', 'name'); // taken by another account

        $second = PlayerName::where('name', 'Second')->firstOrFail();
        $this->assertSame(2, $second->stats()->count());

        $this->actingAs($this->admin)->delete(route('admin.users.names.destroy', [$user, $user->primaryPlayerName()]))
            ->assertSessionHas('error');
        $this->actingAs($this->admin)->delete(route('admin.users.names.destroy', [$user, $second]))
            ->assertSessionHas('status');

        $this->assertSame(['Main'], $user->playerNames()->pluck('name')->all());
    }

    public function test_a_name_can_only_be_removed_through_its_own_account(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $othersName = PlayerName::addFor($other, 'OthersName');

        $this->actingAs($this->admin)
            ->delete(route('admin.users.names.destroy', [$user, $othersName]))
            ->assertNotFound();
    }

    public function test_an_admin_can_send_a_password_reset_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.users.password-reset', $user))->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_accounts_without_replays_can_be_deleted_but_not_with_or_self(): void
    {
        $spam = User::factory()->create();
        $player = User::factory()->create();
        $this->replayRow($player, 'game-1', true, 50);

        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $spam))->assertRedirect(route('admin.users.index'));
        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $player))->assertSessionHas('error');
        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->admin))->assertSessionHas('error');

        $this->assertNull(User::find($spam->id));
        $this->assertSame(0, PlayerName::where('user_id', $spam->id)->count());
        $this->assertNotNull(User::find($player->id));
        $this->assertNotNull(User::find($this->admin->id));
    }

    public function test_invalidating_a_game_reverses_its_elo_and_record_once(): void
    {
        $winner = User::factory()->create();
        $loser = User::factory()->create();
        foreach ([[$winner, 1050, 1, 0], [$loser, 950, 0, 1]] as [$user, $elo, $wins, $losses]) {
            $user->primaryPlayerName()->ensureStats(Season::first());
            $user->primaryPlayerName()->stats()->where('format', '2v2')->update(compact('elo', 'wins', 'losses'));
        }
        $this->replayRow($winner, 'game-1', true, 50);
        $this->replayRow($loser, 'game-1', false, -50);

        $this->actingAs($this->admin)->get('/admin/replays')->assertOk()->assertSee('game-1');

        $this->actingAs($this->admin)->post(route('admin.replays.invalidate', 'game-1'))->assertSessionHas('status');
        $this->actingAs($this->admin)->post(route('admin.replays.invalidate', 'game-1')); // second click is a no-op

        $stat = fn(User $u) => $u->primaryPlayerName()->stats()->where('format', '2v2')->first();
        $this->assertSame([1000, 0, 0], [$stat($winner)->elo, $stat($winner)->wins, $stat($winner)->losses]);
        $this->assertSame([1000, 0, 0], [$stat($loser)->elo, $stat($loser)->wins, $stat($loser)->losses]);
        $this->assertTrue(Replay::where('replay_id', 'game-1')->get()->every->is_invalid);
    }

    public function test_replays_can_be_filtered_by_player(): void
    {
        $alpha = User::factory()->create(['player_name' => 'AlphaPlayer']);
        $bravo = User::factory()->create(['player_name' => 'BravoPlayer']);
        $this->replayRow($alpha, 'game-alpha', true, 10);
        $this->replayRow($bravo, 'game-bravo', true, 10);

        $this->actingAs($this->admin)->get('/admin/replays?player=alpha')
            ->assertOk()->assertSee('AlphaPlayer')->assertDontSee('BravoPlayer');
    }

    public function test_the_admin_link_only_shows_for_admins(): void
    {
        $this->actingAs($this->admin)->get('/rankings')->assertSee(route('admin.users.index'));
        $this->actingAs(User::factory()->create())->get('/rankings')->assertDontSee(route('admin.users.index'));
    }
}
