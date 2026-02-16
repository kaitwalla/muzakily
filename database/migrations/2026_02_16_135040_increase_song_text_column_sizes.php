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
            // Handle long titles (e.g., classical pieces with full movement names)
            $table->text('title')->change();
            $table->text('title_normalized')->change();
            // Handle long artist names (e.g., soundtrack collaborations)
            $table->text('artist_name')->nullable()->change();
            // Handle long album names
            $table->text('album_name')->nullable()->change();
        });

        Schema::table('albums', function (Blueprint $table) {
            $table->text('name')->change();
            $table->text('name_normalized')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->string('title')->change();
            $table->string('title_normalized')->change();
            $table->string('artist_name')->nullable()->change();
            $table->string('album_name')->nullable()->change();
        });

        Schema::table('albums', function (Blueprint $table) {
            $table->string('name')->change();
            $table->string('name_normalized')->change();
        });
    }
};
