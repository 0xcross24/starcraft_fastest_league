<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReplayUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_the_upload_page(): void
    {
        $this->get(route('upload.index'))->assertRedirect(route('login'));
    }

    public function test_guests_cannot_upload(): void
    {
        $this->post(route('upload.upload'), [
            'file' => UploadedFile::fake()->create('game.rep', 10),
        ])->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_the_upload_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('upload.index'))
            ->assertOk();
    }

    public function test_a_file_is_required(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('upload.upload'), [])
            ->assertSessionHasErrors('file');
    }

    public function test_files_larger_than_2mb_are_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('upload.upload'), [
                'file' => UploadedFile::fake()->create('game.rep', 2049),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_non_rep_extensions_are_rejected(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->create())
            ->post(route('upload.upload'), [
                'file' => UploadedFile::fake()->create('notes.txt', 10),
            ])
            ->assertSessionHas('error', 'Unexpected file type.');

        Storage::disk('public')->assertDirectoryEmpty('/');
    }

    public function test_a_rep_file_that_screp_cannot_parse_is_rejected(): void
    {
        Storage::fake('public');

        // Exercises the real screp binary: a .rep that is not a replay makes
        // it exit non-zero, which is the path that must not 500.
        $this->actingAs(User::factory()->create())
            ->post(route('upload.upload'), [
                'file' => UploadedFile::fake()->create('garbage.rep', 10),
            ])
            ->assertSessionHas('error', 'Script execution failed.');

        // Nothing is stored until the replay has parsed and validated.
        Storage::disk('public')->assertDirectoryEmpty('/');
    }

    public function test_guests_cannot_download_a_replay(): void
    {
        $this->get(route('replay.download', 'some-uuid'))
            ->assertRedirect(route('login'));
    }

    public function test_downloading_a_replay_that_does_not_exist_is_not_found(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('replay.download', 'no-such-uuid'))
            ->assertNotFound();
    }
}
