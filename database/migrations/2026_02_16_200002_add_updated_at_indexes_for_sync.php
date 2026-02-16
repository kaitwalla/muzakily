<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->index('updated_at', 'songs_updated_at_index');
        });

        Schema::table('albums', function (Blueprint $table) {
            $table->index('updated_at', 'albums_updated_at_index');
        });

        Schema::table('artists', function (Blueprint $table) {
            $table->index('updated_at', 'artists_updated_at_index');
        });

        Schema::table('playlists', function (Blueprint $table) {
            $table->index('updated_at', 'playlists_updated_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->dropIndex('songs_updated_at_index');
        });

        Schema::table('albums', function (Blueprint $table) {
            $table->dropIndex('albums_updated_at_index');
        });

        Schema::table('artists', function (Blueprint $table) {
            $table->dropIndex('artists_updated_at_index');
        });

        Schema::table('playlists', function (Blueprint $table) {
            $table->dropIndex('playlists_updated_at_index');
        });
    }
};
