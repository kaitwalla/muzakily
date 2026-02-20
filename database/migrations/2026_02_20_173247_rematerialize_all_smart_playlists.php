<?php

use App\Jobs\RefreshSmartPlaylistJob;
use App\Models\Playlist;
use Illuminate\Database\Migrations\Migration;

/**
 * Rematerialize all smart playlists to apply the fix for tag operators.
 *
 * The mobile app uses 'has' and 'has_not' operators for tag rules, but the
 * backend previously only supported 'is', 'is_not', etc. This caused tag
 * rules to be silently skipped during materialization.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Playlist::where('is_smart', true)
            ->whereNotNull('materialized_at')
            ->each(function (Playlist $playlist) {
                RefreshSmartPlaylistJob::dispatch($playlist);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed - playlists will just be rematerialized again
    }
};
