<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\RefreshSmartPlaylistJob;
use App\Jobs\RefreshStaleSmartPlaylistsJob;
use App\Models\Playlist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RefreshStaleSmartPlaylistsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_dispatches_refresh_for_stale_playlists(): void
    {
        $user = User::factory()->create();

        // Create playlists (observer will run and set materialized_at to now)
        $stalePlaylist1 = Playlist::factory()->create([
            'user_id' => $user->id,
            'is_smart' => true,
            'rules' => [['logic' => 'and', 'rules' => [['field' => 'title', 'operator' => 'contains', 'value' => 'Test']]]],
        ]);

        $stalePlaylist2 = Playlist::factory()->create([
            'user_id' => $user->id,
            'is_smart' => true,
            'rules' => [['logic' => 'and', 'rules' => [['field' => 'title', 'operator' => 'contains', 'value' => 'Test2']]]],
        ]);

        $freshPlaylist = Playlist::factory()->create([
            'user_id' => $user->id,
            'is_smart' => true,
            'rules' => [['logic' => 'and', 'rules' => [['field' => 'title', 'operator' => 'contains', 'value' => 'Fresh']]]],
        ]);

        // Regular playlist (should be ignored)
        Playlist::factory()->create([
            'user_id' => $user->id,
            'is_smart' => false,
        ]);

        // Manually set materialized_at timestamps to simulate age
        Playlist::withoutEvents(function () use ($stalePlaylist1, $stalePlaylist2, $freshPlaylist) {
            $stalePlaylist1->update(['materialized_at' => now()->subHours(48)]);
            $stalePlaylist2->update(['materialized_at' => now()->subHours(48)]);
            $freshPlaylist->update(['materialized_at' => now()->subHours(12)]);
        });

        // Start faking AFTER playlists are set up
        Queue::fake([RefreshSmartPlaylistJob::class]);

        $job = new RefreshStaleSmartPlaylistsJob(staleAfterHours: 24);
        $job->handle();

        // Should only dispatch for the 2 stale playlists
        Queue::assertPushed(RefreshSmartPlaylistJob::class, function ($job) use ($stalePlaylist1) {
            return $job->playlist->id === $stalePlaylist1->id;
        });
        Queue::assertPushed(RefreshSmartPlaylistJob::class, function ($job) use ($stalePlaylist2) {
            return $job->playlist->id === $stalePlaylist2->id;
        });
        Queue::assertNotPushed(RefreshSmartPlaylistJob::class, function ($job) use ($freshPlaylist) {
            return $job->playlist->id === $freshPlaylist->id;
        });
    }

    public function test_job_dispatches_refresh_for_unmaterialized_playlists(): void
    {
        $user = User::factory()->create();

        // Create playlist then set materialized_at to null
        $playlist = Playlist::factory()->create([
            'user_id' => $user->id,
            'is_smart' => true,
            'rules' => [['logic' => 'and', 'rules' => [['field' => 'title', 'operator' => 'contains', 'value' => 'Test']]]],
        ]);
        $playlist->update(['materialized_at' => null]);

        // Start faking AFTER playlist is created
        Queue::fake([RefreshSmartPlaylistJob::class]);

        $job = new RefreshStaleSmartPlaylistsJob(staleAfterHours: 24);
        $job->handle();

        Queue::assertPushed(RefreshSmartPlaylistJob::class, function ($job) use ($playlist) {
            return $job->playlist->id === $playlist->id;
        });
    }

    public function test_job_respects_custom_stale_threshold(): void
    {
        $user = User::factory()->create();

        // Create playlist (observer sets materialized_at to now)
        $playlist = Playlist::factory()->create([
            'user_id' => $user->id,
            'is_smart' => true,
            'rules' => [['logic' => 'and', 'rules' => [['field' => 'title', 'operator' => 'contains', 'value' => 'Test']]]],
        ]);

        // Manually set materialized_at to 6 hours ago
        Playlist::withoutEvents(function () use ($playlist) {
            $playlist->update(['materialized_at' => now()->subHours(6)]);
        });

        // Start faking AFTER playlist is set up
        Queue::fake([RefreshSmartPlaylistJob::class]);

        // With 4 hour threshold, this should be considered stale
        $job = new RefreshStaleSmartPlaylistsJob(staleAfterHours: 4);
        $job->handle();

        Queue::assertPushed(RefreshSmartPlaylistJob::class, function ($job) use ($playlist) {
            return $job->playlist->id === $playlist->id;
        });
    }
}
