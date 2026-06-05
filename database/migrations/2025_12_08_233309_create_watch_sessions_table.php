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
        Schema::create('watch_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('device_registration_id')->nullable()->constrained('device_registrations')->onDelete('set null');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('total_watch_time')->default(0); // Total seconds watched in session
            $table->integer('videos_watched')->default(0); // Number of videos watched
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'started_at']);
            $table->index('device_registration_id');
            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('watch_sessions');
    }
};
