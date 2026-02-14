<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\RefreshSmartPlaylistJob;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use App\Services\Playlist\SmartPlaylistEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefreshSmartPlaylistJobTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Artist $artist;

    private Album $album;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->artist = Artist::factory()->create(['name' => 'Test Artist']);
        $this->album = Album::factory()->create(['artist_id' => $this->artist->id]);
    }

    public function test_job_materializes_smart_playlist(): void
    {
        // Create some songs
        $matchingSong = Song::factory()->create([
            'artist_id' => $this->artist->id,
            'album_id' => $this->album->id,
            'artist_name' => 'Test Artist',
        ]);

        $nonMatchingSong = Song::factory()->create([
            'artist_id' => $this->artist->id,
            'album_id' => $this->album->id,
            'artist_name' => 'Other Artist',
        ]);

        // Create a smart playlist that matches artist_name = 'Test Artist'
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
            'rules' => [
                [
                    'id' => 1,
                    'logic' => 'and',
                    'rules' => [
                        [
                            'field' => 'artist_name',
                            'operator' => 'is',
                            'value' => 'Test Artist',
                        ],
                    ],
                ],
            ],
        ]);

        // Run the job
        $job = new RefreshSmartPlaylistJob($playlist);
        $job->handle(app(SmartPlaylistEvaluator::class));

        // Refresh the playlist
        $playlist->refresh();

        // Verify materialized_at is set
        $this->assertNotNull($playlist->materialized_at);

        // Verify the matching song is in the playlist
        $this->assertTrue($playlist->songs()->where('songs.id', $matchingSong->id)->exists());

        // Verify the non-matching song is NOT in the playlist
        $this->assertFalse($playlist->songs()->where('songs.id', $nonMatchingSong->id)->exists());
    }

    public function test_job_clears_previous_materialization(): void
    {
        // Disable observers to have full control of the test
        Song::unsetEventDispatcher();
        Playlist::unsetEventDispatcher();

        // Create a song that will no longer match
        $oldMatchingSong = Song::factory()->create([
            'artist_id' => $this->artist->id,
            'album_id' => $this->album->id,
            'artist_name' => 'Old Artist',
        ]);

        // Create a song that will now match
        $newMatchingSong = Song::factory()->create([
            'artist_id' => $this->artist->id,
            'album_id' => $this->album->id,
            'artist_name' => 'New Artist',
        ]);

        // Create a smart playlist with old rules
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
            'rules' => [
                [
                    'id' => 1,
                    'logic' => 'and',
                    'rules' => [
                        [
                            'field' => 'artist_name',
                            'operator' => 'is',
                            'value' => 'Old Artist',
                        ],
                    ],
                ],
            ],
        ]);

        // Re-enable observers
        Song::setEventDispatcher(app('events'));
        Playlist::setEventDispatcher(app('events'));

        // First materialization
        $job = new RefreshSmartPlaylistJob($playlist);
        $job->handle(app(SmartPlaylistEvaluator::class));

        $this->assertTrue($playlist->songs()->where('songs.id', $oldMatchingSong->id)->exists());

        // Change the rules (disable observer again to prevent auto-refresh)
        Playlist::unsetEventDispatcher();
        $playlist->update([
            'rules' => [
                [
                    'id' => 1,
                    'logic' => 'and',
                    'rules' => [
                        [
                            'field' => 'artist_name',
                            'operator' => 'is',
                            'value' => 'New Artist',
                        ],
                    ],
                ],
            ],
        ]);
        Playlist::setEventDispatcher(app('events'));

        // Second materialization with fresh playlist
        $freshPlaylist = $playlist->fresh();
        $this->assertNotNull($freshPlaylist);
        $job = new RefreshSmartPlaylistJob($freshPlaylist);
        $job->handle(app(SmartPlaylistEvaluator::class));

        // Verify old song is removed
        $this->assertFalse($playlist->songs()->where('songs.id', $oldMatchingSong->id)->exists());

        // Verify new song is added
        $this->assertTrue($playlist->songs()->where('songs.id', $newMatchingSong->id)->exists());
    }

    public function test_job_skips_non_smart_playlists(): void
    {
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => false,
        ]);

        $job = new RefreshSmartPlaylistJob($playlist);
        $job->handle(app(SmartPlaylistEvaluator::class));

        // Verify materialized_at is not set for non-smart playlists
        $this->assertNull($playlist->fresh()->materialized_at);
    }

    public function test_job_handles_empty_rules(): void
    {
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
            'rules' => [],
        ]);

        $job = new RefreshSmartPlaylistJob($playlist);
        $job->handle(app(SmartPlaylistEvaluator::class));

        // Verify materialized_at is set even for empty rules
        $this->assertNotNull($playlist->fresh()->materialized_at);

        // Verify no songs are in the playlist
        $this->assertEquals(0, $playlist->songs()->count());
    }

    public function test_job_unique_id_is_playlist_id(): void
    {
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
        ]);

        $job = new RefreshSmartPlaylistJob($playlist);

        $this->assertEquals($playlist->id, $job->uniqueId());
    }
}
