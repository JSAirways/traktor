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
        // Use try-catch to handle existing indexes gracefully
        try {
            Schema::table('users', function (Blueprint $table) {
                // Index on account_status for filtering approved/pending users
                $table->index('account_status');
            });
        } catch (\Exception $e) {
            // Index might already exist, skip
        }
        
        try {
            Schema::table('videos', function (Blueprint $table) {
                // Index on user_id for faster queries
                $table->index('user_id');
            });
        } catch (\Exception $e) {
            // Index might already exist, skip
        }
        
        try {
            Schema::table('videos', function (Blueprint $table) {
                // Index on is_visible for filtering
                $table->index('is_visible');
            });
        } catch (\Exception $e) {
            // Index might already exist, skip
        }
        
        try {
            Schema::table('playlists', function (Blueprint $table) {
                // Index on user_id for faster queries
                $table->index('user_id');
            });
        } catch (\Exception $e) {
            // Index might already exist, skip
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['account_status']);
        });
        
        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['is_visible']);
        });
        
        try {
            Schema::table('playlists', function (Blueprint $table) {
                $table->dropIndex(['user_id']);
            });
        } catch (\Exception $e) {
            // Index might not exist, skip
        }
    }
};
