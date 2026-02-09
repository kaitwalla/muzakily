<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 7)->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('tags')->nullOnDelete();
            $table->unsignedTinyInteger('depth')->default(1);
            $table->boolean('is_special')->default(false);
            $table->unsignedInteger('song_count')->default(0);
            $table->string('auto_assign_pattern')->nullable();
            $table->timestamps();

            $table->index('parent_id');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
