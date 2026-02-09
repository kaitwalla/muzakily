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
        Schema::create('songs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('album_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('artist_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('smart_folder_id')->nullable()->constrained('smart_folders')->nullOnDelete();
            $table->string('title');
            $table->string('title_normalized');
            $table->string('album_name')->nullable();
            $table->string('artist_name')->nullable();
            $table->decimal('length', 10, 2)->default(0);
            $table->unsignedSmallInteger('track')->nullable();
            $table->unsignedSmallInteger('disc')->default(1);
            $table->unsignedSmallInteger('year')->nullable();
            $table->text('lyrics')->nullable();
            $table->string('storage_path', 512)->unique();
            $table->string('file_hash', 64)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('mime_type', 50)->nullable();
            $table->string('audio_format', 10);
            $table->string('r2_etag')->nullable();
            $table->timestamp('r2_last_modified')->nullable();
            $table->string('musicbrainz_id')->nullable();
            $table->unsignedInteger('mtime')->nullable();
            $table->timestamps();

            $table->index('album_id');
            $table->index('artist_id');
            $table->index('smart_folder_id');
            $table->index('title_normalized');
            $table->index('file_hash');
            $table->index('year');
            $table->index('audio_format');
        });

        // Add composite B-tree index for ILIKE searches (actual full-text search via tsvector is added in a later migration)
        if (config('database.default') === 'pgsql') {
            Schema::table('songs', function (Blueprint $table) {
                $table->index(['title', 'artist_name', 'album_name'], 'songs_text_columns');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('songs');
    }
};
