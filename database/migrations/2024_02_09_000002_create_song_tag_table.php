<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('song_tag', function (Blueprint $table) {
            $table->uuid('song_id');
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->boolean('auto_assigned')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['song_id', 'tag_id']);
            $table->index('tag_id');

            $table->foreign('song_id')->references('id')->on('songs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('song_tag');
    }
};
