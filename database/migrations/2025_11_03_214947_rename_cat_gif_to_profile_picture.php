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
        Schema::table('users', function (Blueprint $table) {
            // Rename cat_gif to profile_picture
            $table->renameColumn('cat_gif', 'profile_picture');
            
            // Add profile_picture_category column
            $table->string('profile_picture_category')->nullable()->after('profile_picture');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('profile_picture', 'cat_gif');
            $table->dropColumn('profile_picture_category');
        });
    }
};
