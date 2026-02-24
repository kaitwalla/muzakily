<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('playlists', function (Blueprint $table) {
            $table->integer('position')->default(0);
            $table->index(['user_id', 'position']);
        });

        // Initialize existing playlists with positions based on created_at order per user
        DB::statement('
            UPDATE playlists
            SET position = ordered.row_num
            FROM (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY created_at, id) - 1 as row_num
                FROM playlists
            ) as ordered
            WHERE playlists.id = ordered.id
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('playlists', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'position']);
            $table->dropColumn('position');
        });
    }
};
