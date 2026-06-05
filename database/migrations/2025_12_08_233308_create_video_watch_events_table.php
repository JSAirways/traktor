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
        Schema::create('video_watch_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('video_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('playlist_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('device_registration_id')->nullable()->constrained('device_registrations')->onDelete('set null');
            $table->string('event_type', 50); // 'started', 'paused', 'resumed', 'completed', 'abandoned', 'position_update'
            $table->integer('position')->default(0); // Position in seconds
            $table->integer('duration')->nullable(); // Video duration in seconds
            $table->decimal('completion_percentage', 5, 2)->nullable(); // 0-100
            $table->uuid('session_id')->nullable(); // UUID for grouping events in same session
            $table->timestamp('created_at');
            
            // Indexes for performance
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'event_type', 'created_at']); // Composite index for common query pattern
            $table->index(['user_id', 'video_id']);
            $table->index(['user_id', 'playlist_id']);
            $table->index('device_registration_id');
            $table->index('session_id');
            $table->index('event_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_watch_events');
    }
};
