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
        Schema::create('albums', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('artist_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('name_normalized');
            $table->string('cover')->nullable();
            $table->integer('year')->nullable();
            $table->string('musicbrainz_id')->nullable();
            $table->timestamps();

            $table->index('artist_id');
            $table->index('name_normalized');
            $table->index('year');
            $table->index('musicbrainz_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('albums');
    }
};
