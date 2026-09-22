<?php

namespace Tests\Feature;

use App\Models\BuildOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildOrderStepsTest extends TestCase
{
    use RefreshDatabase;

    private function build(string $steps): BuildOrder
    {
        return BuildOrder::create([
            'title' => 'Six Rax',
            'race' => 'Terran',
            'matchup' => ['PUB'],
            'steps' => $steps,
        ]);
    }

    public function test_a_run_of_dash_lines_becomes_a_list(): void
    {
        $html = $this->build("- first\n- second")->steps_html;

        $this->assertStringContainsString('<ul', $html);
        $this->assertStringContainsString('<li>first</li>', $html);
        $this->assertStringContainsString('<li>second</li>', $html);
        $this->assertSame(1, substr_count($html, '<ul'));
    }

    public function test_plain_lines_keep_their_breaks_outside_the_list(): void
    {
        $html = $this->build("8 Depot\n9 Barracks")->steps_html;

        $this->assertStringContainsString("<p>8 Depot\n9 Barracks</p>", $html);
        $this->assertStringNotContainsString('<ul', $html);
    }

    public function test_timings_then_notes_render_as_a_block_then_a_list(): void
    {
        $html = $this->build("4:10 ebay\n\nNotes:\n- stay on 4 fact\n- anti air is light")->steps_html;

        $this->assertStringContainsString('4:10 ebay', $html);
        $this->assertStringContainsString('Notes:', $html);
        $this->assertStringContainsString('<li>stay on 4 fact</li>', $html);

        // The blank line between the two blocks must not leave the list
        // trailing an empty paragraph line.
        $this->assertStringNotContainsString("Notes:\n</p>", $html);
        $this->assertLessThan(
            strpos($html, '<ul'),
            strpos($html, 'Notes:'),
            'Notes: should introduce the list, not follow it.'
        );
    }

    public function test_the_list_marker_itself_is_not_printed(): void
    {
        $html = $this->build('- stay on 4 fact')->steps_html;

        $this->assertStringContainsString('<li>stay on 4 fact</li>', $html);
        $this->assertStringNotContainsString('- stay', $html);
    }

    public function test_admin_typed_html_is_escaped_not_rendered(): void
    {
        $html = $this->build("<script>alert(1)</script>\n- <b>bold</b>")->steps_html;

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<b>bold</b>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;bold&lt;/b&gt;', $html);
    }

    public function test_empty_steps_produce_no_markup(): void
    {
        $this->assertSame('', $this->build('')->steps_html);
    }

    public function test_the_show_page_renders_the_bullets(): void
    {
        $build = $this->build("4:10 ebay\n\nNotes:\n- stay on 4 fact");

        $this->get(route('builds.show', $build->id))
            ->assertOk()
            ->assertSee('<li>stay on 4 fact</li>', false)
            ->assertDontSee('- stay on 4 fact');
    }
}
