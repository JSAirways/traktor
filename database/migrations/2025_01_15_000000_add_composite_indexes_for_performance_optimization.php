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
        if (Schema::hasTable('videos')) {
            Schema::table('videos', function (Blueprint $table) {
                // Composite index for common query patterns
                $table->index(['user_id', 'channel_id', 'is_visible', 'display_order'], 'idx_videos_user_channel_visible_order');
                $table->index(['playlist_id', 'is_visible', 'display_order'], 'idx_videos_playlist_visible_order');
            });
        }

        if (Schema::hasTable('playlists')) {
            Schema::table('playlists', function (Blueprint $table) {
                // Composite index for common query patterns
                $table->index(['user_id', 'channel_id', 'is_visible', 'display_order'], 'idx_playlists_user_channel_visible_order');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('videos')) {
            Schema::table('videos', function (Blueprint $table) {
                $table->dropIndex('idx_videos_user_channel_visible_order');
                $table->dropIndex('idx_videos_playlist_visible_order');
            });
        }

        if (Schema::hasTable('playlists')) {
            Schema::table('playlists', function (Blueprint $table) {
                $table->dropIndex('idx_playlists_user_channel_visible_order');
            });
        }
    }
};

