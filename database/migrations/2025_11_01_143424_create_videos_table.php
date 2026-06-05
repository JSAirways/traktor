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
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('video_id'); // YouTube video ID
            $table->string('title');
            $table->integer('duration'); // Duration in seconds
            $table->string('thumbnail_url');
            $table->integer('display_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->foreignId('playlist_id')->nullable()->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->index(['user_id', 'is_visible']);
            $table->index('display_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
