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
        // Add channel tracking to videos table
        Schema::table('videos', function (Blueprint $table) {
            $table->string('channel_id')->nullable()->after('user_id');
            $table->string('channel_name')->nullable()->after('channel_id');
            $table->string('channel_thumbnail')->nullable()->after('channel_name');
            
            $table->index('channel_id');
        });
        
        // Add channel tracking to playlists table
        Schema::table('playlists', function (Blueprint $table) {
            $table->string('channel_id')->nullable()->after('user_id');
            $table->string('channel_name')->nullable()->after('channel_id');
            $table->string('channel_thumbnail')->nullable()->after('channel_name');
            
            $table->index('channel_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex(['channel_id']);
            $table->dropColumn(['channel_id', 'channel_name', 'channel_thumbnail']);
        });
        
        Schema::table('playlists', function (Blueprint $table) {
            $table->dropIndex(['channel_id']);
            $table->dropColumn(['channel_id', 'channel_name', 'channel_thumbnail']);
        });
    }
};
