<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\UpdateSmartPlaylistsForSongJob;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use App\Services\Playlist\SmartPlaylistEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateSmartPlaylistsForSongJobTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Artist $artist;

    private Album $album;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->artist = Artist::factory()->create();
        $this->album = Album::factory()->create(['artist_id' => $this->artist->id]);
    }

    public function test_job_adds_matching_song_to_materialized_playlist(): void
    {
        // Create a materialized smart playlist
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
            'materialized_at' => now(),
            'rules' => [
                [
                    'id' => 1,
                    'logic' => 'and',
                    'rules' => [
                        [
                            'field' => 'artist_name',
                            'operator' => 'is',
                            'value' => 'Target Artist',
                        ],
                    ],
                ],
            ],
        ]);

        // Create a song that matches
        $song = Song::factory()->create([
            'artist_id' => $this->artist->id,
            'album_id' => $this->album->id,
            'artist_name' => 'Target Artist',
        ]);

        // Run the job
        $job = new UpdateSmartPlaylistsForSongJob($song);
        $job->handle(app(SmartPlaylistEvaluator::class));

        // Verify the song is in the playlist
        $this->assertTrue($playlist->songs()->where('songs.id', $song->id)->exists());
    }

    public function test_job_removes_non_matching_song_from_materialized_playlist(): void
    {
        // Create a song
        $song = Song::factory()->create([
            'artist_id' => $this->artist->id,
            'album_id' => $this->album->id,
            'artist_name' => 'Wrong Artist',
        ]);

        // Create a materialized smart playlist with the song already attached
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
            'materialized_at' => now(),
            'rules' => [
                [
                    'id' => 1,
                    'logic' => 'and',
                    'rules' => [
                        [
                            'field' => 'artist_name',
                            'operator' => 'is',
                            'value' => 'Target Artist',
                        ],
                    ],
                ],
            ],
        ]);

        // Manually add the song to the playlist (simulating it was there before)
        $playlist->songs()->attach($song->id, [
            'position' => 0,
            'created_at' => now(),
        ]);

        $this->assertTrue($playlist->songs()->where('songs.id', $song->id)->exists());

        // Run the job
        $job = new UpdateSmartPlaylistsForSongJob($song);
        $job->handle(app(SmartPlaylistEvaluator::class));

        // Verify the song is removed from the playlist
        $this->assertFalse($playlist->songs()->where('songs.id', $song->id)->exists());
    }

    public function test_job_skips_non_materialized_playlists(): void
    {
        // Disable observers temporarily to create a non-materialized playlist
        Playlist::unsetEventDispatcher();

        // Create a non-materialized smart playlist
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
            'materialized_at' => null, // Not materialized
            'rules' => [
                [
                    'id' => 1,
                    'logic' => 'and',
                    'rules' => [
                        [
                            'field' => 'artist_name',
                            'operator' => 'is',
                            'value' => 'Target Artist',
                        ],
                    ],
                ],
            ],
        ]);

        // Re-enable observers
        Playlist::setEventDispatcher(app('events'));

        // Disable song observers to prevent automatic job dispatch
        Song::unsetEventDispatcher();

        // Create a song that matches
        $song = Song::factory()->create([
            'artist_id' => $this->artist->id,
            'album_id' => $this->album->id,
            'artist_name' => 'Target Artist',
        ]);

        // Re-enable observers
        Song::setEventDispatcher(app('events'));

        // Run the job manually
        $job = new UpdateSmartPlaylistsForSongJob($song);
        $job->handle(app(SmartPlaylistEvaluator::class));

        // Verify the song is NOT in the playlist (because it's not materialized)
        $this->assertFalse($playlist->songs()->where('songs.id', $song->id)->exists());
    }

    public function test_job_removes_song_when_removing_flag_is_true(): void
    {
        // Disable observers to have full control
        Song::unsetEventDispatcher();
        Playlist::unsetEventDispatcher();

        // Create a song
        $song = Song::factory()->create([
            'artist_id' => $this->artist->id,
            'album_id' => $this->album->id,
            'artist_name' => 'Target Artist',
        ]);

        // Create a materialized smart playlist
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
            'materialized_at' => now(),
            'rules' => [
                [
                    'id' => 1,
                    'logic' => 'and',
                    'rules' => [
                        [
                            'field' => 'artist_name',
                            'operator' => 'is',
                            'value' => 'Target Artist',
                        ],
                    ],
                ],
            ],
        ]);

        // Manually attach the song
        $playlist->songs()->attach($song->id, [
            'position' => 0,
            'created_at' => now(),
        ]);

        // Re-enable observers
        Song::setEventDispatcher(app('events'));
        Playlist::setEventDispatcher(app('events'));

        // Verify the song is in the playlist
        $this->assertTrue($playlist->songs()->where('songs.id', $song->id)->exists());

        // Run the job with removing flag
        $job = new UpdateSmartPlaylistsForSongJob($song, removing: true);
        $job->handle(app(SmartPlaylistEvaluator::class));

        // Verify the song is removed
        $this->assertFalse($playlist->songs()->where('songs.id', $song->id)->exists());
    }

    public function test_job_unique_id_is_song_id(): void
    {
        $song = Song::factory()->create([
            'artist_id' => $this->artist->id,
            'album_id' => $this->album->id,
        ]);

        $job = new UpdateSmartPlaylistsForSongJob($song);

        $this->assertEquals($song->id, $job->uniqueId());
    }

    public function test_job_handles_multiple_playlists(): void
    {
        // Create a song that matches
        $song = Song::factory()->create([
            'artist_id' => $this->artist->id,
            'album_id' => $this->album->id,
            'artist_name' => 'Common Artist',
        ]);

        // Create multiple materialized smart playlists
        $matchingPlaylist1 = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
            'materialized_at' => now(),
            'rules' => [
                [
                    'id' => 1,
                    'logic' => 'and',
                    'rules' => [
                        [
                            'field' => 'artist_name',
                            'operator' => 'is',
                            'value' => 'Common Artist',
                        ],
                    ],
                ],
            ],
        ]);

        $matchingPlaylist2 = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
            'materialized_at' => now(),
            'rules' => [
                [
                    'id' => 1,
                    'logic' => 'and',
                    'rules' => [
                        [
                            'field' => 'artist_name',
                            'operator' => 'contains',
                            'value' => 'Common',
                        ],
                    ],
                ],
            ],
        ]);

        $nonMatchingPlaylist = Playlist::factory()->create([
            'user_id' => $this->user->id,
            'is_smart' => true,
            'materialized_at' => now(),
            'rules' => [
                [
                    'id' => 1,
                    'logic' => 'and',
                    'rules' => [
                        [
                            'field' => 'artist_name',
                            'operator' => 'is',
                            'value' => 'Other Artist',
                        ],
                    ],
                ],
            ],
        ]);

        // Run the job
        $job = new UpdateSmartPlaylistsForSongJob($song);
        $job->handle(app(SmartPlaylistEvaluator::class));

        // Verify the song is in the matching playlists
        $this->assertTrue($matchingPlaylist1->songs()->where('songs.id', $song->id)->exists());
        $this->assertTrue($matchingPlaylist2->songs()->where('songs.id', $song->id)->exists());

        // Verify the song is NOT in the non-matching playlist
        $this->assertFalse($nonMatchingPlaylist->songs()->where('songs.id', $song->id)->exists());
    }
}
