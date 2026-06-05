<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add performance indexes for frequently queried columns.
     */
    public function up(): void
    {
        // Add index on users.slug (used in many queries)
        Schema::table('users', function (Blueprint $table) {
            if (!$this->indexExists('users', 'users_slug_index')) {
                $table->index('slug', 'users_slug_index');
            }
        });

        // Add composite index on videos table for common query patterns
        Schema::table('videos', function (Blueprint $table) {
            if (!$this->indexExists('videos', 'idx_videos_user_visible_playlist')) {
                $table->index(['user_id', 'is_visible', 'playlist_id'], 'idx_videos_user_visible_playlist');
            }
        });

        // Add composite index on playlists table for common query patterns
        Schema::table('playlists', function (Blueprint $table) {
            if (!$this->indexExists('playlists', 'idx_playlists_user_visible')) {
                $table->index(['user_id', 'is_visible'], 'idx_playlists_user_visible');
            }
        });

        // Add composite index on device_child_visibility for common queries
        Schema::table('device_child_visibility', function (Blueprint $table) {
            if (!$this->indexExists('device_child_visibility', 'idx_device_child_visibility_device_visible')) {
                $table->index(['device_registration_id', 'is_visible'], 'idx_device_child_visibility_device_visible');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_child_visibility', function (Blueprint $table) {
            $table->dropIndex('idx_device_child_visibility_device_visible');
        });

        Schema::table('playlists', function (Blueprint $table) {
            $table->dropIndex('idx_playlists_user_visible');
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex('idx_videos_user_visible_playlist');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_slug_index');
        });
    }

    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        
        $result = $connection->select(
            "SELECT COUNT(*) as count FROM information_schema.statistics 
             WHERE table_schema = ? AND table_name = ? AND index_name = ?",
            [$databaseName, $table, $index]
        );
        
        return $result[0]->count > 0;
    }
};
