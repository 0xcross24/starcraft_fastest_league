<?php

namespace Tests\Feature;

use App\Models\DiscordMessage;
use App\Models\Season;
use App\Models\Stats;
use App\Models\User;
use App\Services\DiscordRankings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DiscordRankingsTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = 'https://discord.test/api/webhooks/1/tok';

    private Season $season;

    protected function setUp(): void
    {
        parent::setUp();
        $this->season = Season::create(['is_active' => 1]);
    }

    private function player(string $name, int $elo, int $wins, int $losses, string $format = '2v2'): User
    {
        $user = User::factory()->create(['player_name' => $name]);
        $user->primaryPlayerName()->ensureStats($this->season);
        $user->stats()->where('format', $format)->update(compact('elo', 'wins', 'losses'));

        return $user;
    }

    private function webhookMode(): void
    {
        config(['services.discord.rankings_webhook' => self::WEBHOOK]);
    }

    private function botMode(): void
    {
        config([
            'services.discord.rankings_bot_token' => 'bot-token',
            'services.discord.rankings_channel_id' => '555',
        ]);
    }

    private function sync(bool $force = false): string
    {
        return app(DiscordRankings::class)->sync($force);
    }

    public function test_does_nothing_when_not_configured(): void
    {
        Http::fake();
        $this->player('A', 1100, 1, 0);

        $this->assertSame('off', $this->sync());
        DiscordRankings::refreshSoon();
        $this->app->terminate();

        Http::assertNothingSent();
    }

    public function test_webhook_posts_both_formats_top_five_who_played(): void
    {
        $this->webhookMode();
        Http::fake(['discord.test/*' => Http::response(['id' => '900'])]);
        foreach ([['A', 1400, 9, 1], ['B', 1300, 7, 3], ['C', 1200, 5, 5], ['D', 1150, 4, 4],
                  ['E', 1100, 3, 3], ['F', 1050, 2, 2]] as [$n, $elo, $w, $l]) {
            $this->player($n, $elo, $w, $l);
        }
        $this->player('Wk`Cross_[x]', 1250, 2, 0, '3v3');
        $this->player('NoGames', 1000, 0, 0);

        $this->assertSame('posted', $this->sync());

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            $payload = $request->data();
            [$two, $three] = $payload['embeds'];
            $lines = explode("\n", $two['description']);

            return $request->method() === 'POST'
                && $request->url() === self::WEBHOOK . '?wait=true&with_components=true'
                && $two['title'] === '🏆 Season 1 · 2v2 Top 5'
                && str_starts_with($lines[0], '🥇 **A** — 1400 (')
                && str_starts_with($lines[3], '**#4** **D**')
                && str_starts_with($lines[4], '**#5** **E**')
                && !str_contains($two['description'], '**F**')
                && !str_contains($two['description'], 'NoGames')
                && str_contains($two['description'], 'updated <t:')
                && str_contains($three['description'], '**Wk\\`Cross\\_\\[x\\]** — 1250')
                && $payload['components'][0]['components'][0]['url'] === route('rankings', ['format' => '2v2'])
                && $payload['allowed_mentions'] === ['parse' => []];
        });
        $this->assertSame('900', DiscordMessage::first()->message_id);
    }

    public function test_edits_the_same_message_only_when_standings_change(): void
    {
        $this->webhookMode();
        Http::fake(['discord.test/*' => Http::response(['id' => '900'])]);
        $user = $this->player('A', 1100, 1, 0);

        $this->assertSame('posted', $this->sync());
        $this->assertSame('unchanged', $this->sync());
        Http::assertSentCount(1);

        $user->stats()->where('format', '2v2')->update(['elo' => 1150, 'wins' => 2]);
        $this->assertSame('edited', $this->sync());

        Http::assertSentCount(2);
        Http::assertSent(fn(Request $r) => $r->method() === 'PATCH'
            && $r->url() === self::WEBHOOK . '/messages/900?with_components=true'
            && str_contains($r->data()['embeds'][0]['description'], '1150'));
        $this->assertSame(1, DiscordMessage::count());
    }

    public function test_a_deleted_message_is_posted_again(): void
    {
        $this->webhookMode();
        $this->player('A', 1100, 1, 0);
        DiscordMessage::create(['key' => DiscordRankings::KEY, 'message_id' => '800', 'via' => 'webhook', 'hash' => 'old']);
        Http::fake(fn(Request $r) => $r->method() === 'PATCH'
            ? Http::response(['message' => 'Unknown Message'], 404)
            : Http::response(['id' => '901']));

        $this->assertSame('posted', $this->sync());
        $this->assertSame('901', DiscordMessage::first()->message_id);
    }

    public function test_failures_are_logged_not_thrown(): void
    {
        $this->webhookMode();
        $this->player('A', 1100, 1, 0);
        Http::fake(['discord.test/*' => Http::response('nope', 500)]);
        Log::spy();

        $this->assertSame('failed', $this->sync());
        Log::shouldHaveReceived('warning')->once();
        $this->assertSame(0, DiscordMessage::count());
    }

    public function test_bot_posts_one_format_with_buttons_and_replaces_the_webhook_message(): void
    {
        $this->webhookMode();
        $this->botMode();
        $this->player('A', 1100, 1, 0);
        DiscordMessage::create(['key' => DiscordRankings::KEY, 'message_id' => '800', 'via' => 'webhook', 'hash' => 'x']);
        Http::fake(['*' => Http::response(['id' => '777'])]);

        $this->assertSame('posted', $this->sync());

        Http::assertSent(fn(Request $r) => $r->method() === 'DELETE' && $r->url() === self::WEBHOOK . '/messages/800');
        Http::assertSent(function (Request $r) {
            $buttons = $r->data()['components'][0]['components'] ?? [];

            return $r->method() === 'POST'
                && $r->url() === 'https://discord.com/api/v10/channels/555/messages'
                && $r->hasHeader('Authorization', 'Bot bot-token')
                && count($r->data()['embeds']) === 1
                && $buttons[0]['custom_id'] === 'rankings:2v2' && $buttons[0]['disabled'] === true
                && $buttons[1]['custom_id'] === 'rankings:3v3' && $buttons[1]['disabled'] === false
                && $buttons[2]['style'] === 5;
        });
        $post = DiscordMessage::first();
        $this->assertSame(['777', 'bot'], [$post->message_id, $post->via]);
    }

    public function test_refresh_soon_syncs_once_after_the_request(): void
    {
        $this->webhookMode();
        Http::fake(['discord.test/*' => Http::response(['id' => '900'])]);
        $this->player('A', 1100, 1, 0);

        DiscordRankings::refreshSoon();
        DiscordRankings::refreshSoon();
        Http::assertNothingSent();

        $this->app->terminate();
        Http::assertSentCount(1);
    }

    public function test_invalidating_a_game_updates_the_message(): void
    {
        $this->webhookMode();
        Http::fake(['discord.test/*' => Http::response(['id' => '900'])]);
        $user = $this->player('A', 1100, 1, 0);
        $replay = \App\Models\Replay::create([
            'replay_id' => 'game-1', 'player_name' => 'A', 'user_id' => $user->id,
            'player_name_id' => $user->primaryPlayerName()->id, 'season_id' => $this->season->id,
            'format' => '2v2', 'winning_team' => 1, 'points' => 100, 'replay_file' => 'x.rep', 'hash' => 'h',
            'team' => '1', 'race' => 'Zerg', 'apm' => 1, 'eapm' => 1,
        ]);

        app(\App\Services\ReplayInvalidator::class)->invalidate(collect([$replay]));
        $this->app->terminate();

        Http::assertSent(fn(Request $r) => str_contains($r->data()['embeds'][0]['description'] ?? '', 'No ranked games yet'));
    }

    public function test_schedule_runs_the_sync_every_five_minutes(): void
    {
        $this->artisan('schedule:list')->assertSuccessful(); // registers the app's schedule
        $event = collect(app(Schedule::class)->events())
            ->first(fn($e) => str_contains((string) $e->command, 'rankings:discord'));

        $this->assertSame('*/5 * * * *', $event?->expression);
    }

    // Interactions endpoint

    private string $keys;

    private function signedPost(array $body, ?string $secret = null)
    {
        $json = json_encode($body);
        $timestamp = (string) time();
        $signature = bin2hex(sodium_crypto_sign_detached($timestamp . $json, $secret ?? sodium_crypto_sign_secretkey($this->keys)));

        return $this->call('POST', '/api/discord/interactions', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE_ED25519' => $signature,
            'HTTP_X_SIGNATURE_TIMESTAMP' => $timestamp,
        ], $json);
    }

    private function withAppKey(): void
    {
        $this->keys = sodium_crypto_sign_keypair();
        config(['services.discord.rankings_public_key' => bin2hex(sodium_crypto_sign_publickey($this->keys))]);
    }

    public function test_interactions_reject_bad_or_missing_signatures(): void
    {
        $this->withAppKey();

        $this->postJson('/api/discord/interactions', ['type' => 1])->assertStatus(401);
        $this->signedPost(['type' => 1], sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair()))->assertStatus(401);
    }

    public function test_interactions_answer_discords_ping(): void
    {
        $this->withAppKey();

        $this->signedPost(['type' => 1])->assertOk()->assertExactJson(['type' => 1]);
    }

    public function test_button_flips_the_message_and_later_edits_keep_that_format(): void
    {
        $this->withAppKey();
        $this->botMode();
        Http::fake(['*' => Http::response(['id' => '777'])]);
        $this->player('A', 1100, 1, 0);
        $this->player('B', 1200, 2, 0, '3v3');
        $this->sync();

        $response = $this->signedPost(['type' => 3, 'data' => ['custom_id' => 'rankings:3v3'], 'message' => ['id' => '777']])
            ->assertOk()->assertJsonPath('type', 7);

        $data = $response->json('data');
        $this->assertSame('🏆 Season 1 · 3v3 Top 5', $data['embeds'][0]['title']);
        $this->assertStringContainsString('**B**', $data['embeds'][0]['description']);
        $this->assertTrue($data['components'][0]['components'][1]['disabled']);
        $this->assertSame('3v3', DiscordMessage::first()->format);

        $this->sync(force: true);
        Http::assertSent(fn(Request $r) => $r->method() === 'PATCH'
            && str_contains($r->data()['embeds'][0]['title'], '3v3'));
    }
}
