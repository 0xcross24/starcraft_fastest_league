<?php

namespace Tests\Feature;

use App\Models\BuildOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BuildOrderVideoTest extends TestCase
{
    use RefreshDatabase;

    private function buildWithVideo(?string $url): BuildOrder
    {
        return BuildOrder::create([
            'title' => 'With video',
            'race' => 'Protoss',
            'matchup' => ['PUB'],
            'steps' => '7 Pylon',
            'youtube_url' => $url,
        ]);
    }

    public static function urlProvider(): array
    {
        return [
            'short link' => ['https://youtu.be/uJ_-qEyE3es'],
            'short link with query' => ['https://youtu.be/uJ_-qEyE3es?t=42'],
            'watch link' => ['https://www.youtube.com/watch?v=uJ_-qEyE3es'],
            'watch link with extra params' => ['https://www.youtube.com/watch?list=abc&v=uJ_-qEyE3es&t=1'],
            'embed link' => ['https://www.youtube.com/embed/uJ_-qEyE3es'],
            'shorts link' => ['https://www.youtube.com/shorts/uJ_-qEyE3es'],
            'bare id' => ['uJ_-qEyE3es'],
        ];
    }

    #[DataProvider('urlProvider')]
    public function test_it_extracts_the_video_id(string $url): void
    {
        $this->assertSame('uJ_-qEyE3es', $this->buildWithVideo($url)->youtube_embed_id);
    }

    public function test_it_returns_null_when_there_is_no_video(): void
    {
        $this->assertNull($this->buildWithVideo(null)->youtube_embed_id);
        $this->assertNull($this->buildWithVideo('')->youtube_embed_id);
    }

    public function test_it_returns_null_for_something_that_is_not_a_youtube_link(): void
    {
        $this->assertNull($this->buildWithVideo('https://example.com/not-a-video')->youtube_embed_id);
    }

    public function test_the_player_embeds_the_id_not_the_raw_url(): void
    {
        $build = $this->buildWithVideo('https://youtu.be/uJ_-qEyE3es');

        $this->get(route('builds.show', $build->id))
            ->assertOk()
            ->assertSee('https://www.youtube.com/embed/uJ_-qEyE3es', false)
            ->assertDontSee('embed/https://youtu.be', false);
    }

    public function test_no_player_is_rendered_without_a_usable_video(): void
    {
        $build = $this->buildWithVideo('https://example.com/not-a-video');

        $this->get(route('builds.show', $build->id))
            ->assertOk()
            ->assertDontSee('youtube.com/embed', false);
    }
}
