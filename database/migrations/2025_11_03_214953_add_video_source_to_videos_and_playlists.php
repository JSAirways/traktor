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
        Schema::table('videos', function (Blueprint $table) {
            // Add video_source enum column (default: youtube for existing records)
            $table->enum('video_source', ['youtube', 'vimeo', 'dailymotion'])->default('youtube')->after('video_id');
            
            // Add source_config JSON column for source-specific settings
            $table->json('source_config')->nullable()->after('video_source');
            
            // Add index for performance
            $table->index('video_source');
        });
        
        Schema::table('playlists', function (Blueprint $table) {
            // Add video_source enum column (default: youtube for existing records)
            $table->enum('video_source', ['youtube', 'vimeo', 'dailymotion'])->default('youtube')->after('playlist_id');
            
            // Add source_config JSON column for source-specific settings
            $table->json('source_config')->nullable()->after('video_source');
            
            // Add index for performance
            $table->index('video_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex(['video_source']);
            $table->dropColumn(['video_source', 'source_config']);
        });
        
        Schema::table('playlists', function (Blueprint $table) {
            $table->dropIndex(['video_source']);
            $table->dropColumn(['video_source', 'source_config']);
        });
    }
};
