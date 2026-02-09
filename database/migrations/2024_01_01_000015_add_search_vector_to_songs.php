<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (config('database.default') !== 'pgsql') {
            return;
        }

        // Add tsvector column for full-text search
        DB::statement('ALTER TABLE songs ADD COLUMN search_vector tsvector');

        // Create GIN index on search_vector
        DB::statement('CREATE INDEX idx_songs_search ON songs USING GIN(search_vector)');

        // Create function to update search vector
        DB::statement("
            CREATE OR REPLACE FUNCTION songs_search_trigger() RETURNS trigger AS \$\$
            BEGIN
                NEW.search_vector :=
                    setweight(to_tsvector('english', COALESCE(NEW.title, '')), 'A') ||
                    setweight(to_tsvector('english', COALESCE(NEW.artist_name, '')), 'B') ||
                    setweight(to_tsvector('english', COALESCE(NEW.album_name, '')), 'C');
                RETURN NEW;
            END
            \$\$ LANGUAGE plpgsql
        ");

        // Create trigger to automatically update search vector
        DB::statement("
            CREATE TRIGGER songs_search_update
            BEFORE INSERT OR UPDATE ON songs
            FOR EACH ROW EXECUTE FUNCTION songs_search_trigger()
        ");

        // Backfill search_vector for existing rows
        DB::statement("
            UPDATE songs SET search_vector =
                setweight(to_tsvector('english', COALESCE(title, '')), 'A') ||
                setweight(to_tsvector('english', COALESCE(artist_name, '')), 'B') ||
                setweight(to_tsvector('english', COALESCE(album_name, '')), 'C')
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') !== 'pgsql') {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS songs_search_update ON songs');
        DB::statement('DROP FUNCTION IF EXISTS songs_search_trigger()');
        DB::statement('DROP INDEX IF EXISTS idx_songs_search');
        DB::statement('ALTER TABLE songs DROP COLUMN IF EXISTS search_vector');
    }
};
