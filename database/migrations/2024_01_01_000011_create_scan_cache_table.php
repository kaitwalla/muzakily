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
        Schema::create('scan_cache', function (Blueprint $table) {
            $table->id();
            $table->string('bucket');
            $table->string('object_key', 512);
            $table->string('key_hash', 64);
            $table->string('etag')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamp('last_modified')->nullable();
            $table->timestamp('last_scanned_at')->nullable();

            $table->unique(['bucket', 'key_hash']);
            $table->index('etag');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scan_cache');
    }
};
