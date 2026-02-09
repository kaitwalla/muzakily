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
        Schema::create('smart_folders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('path_prefix')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('smart_folders')->nullOnDelete();
            $table->integer('depth')->default(1);
            $table->boolean('is_special')->default(false);
            $table->unsignedInteger('song_count')->default(0);
            $table->timestamps();

            $table->index('parent_id');
            $table->index('name');
            $table->index('depth');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('smart_folders');
    }
};
