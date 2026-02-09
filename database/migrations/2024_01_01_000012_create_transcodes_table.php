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
        Schema::create('transcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('song_id')->constrained()->cascadeOnDelete();
            $table->string('format', 10);
            $table->unsignedSmallInteger('bitrate');
            $table->string('storage_key', 512);
            $table->unsignedBigInteger('file_size');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['song_id', 'format', 'bitrate']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transcodes');
    }
};
