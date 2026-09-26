<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            DB::statement('ALTER TABLE users DROP INDEX unique_child_username_per_parent');
        } catch (\Exception $e) {
            // Index might not exist
        }

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('username', 'profile_name');
        });

        DB::statement('ALTER TABLE users ADD UNIQUE KEY unique_child_profile_name_per_parent (parent_id, profile_name)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE users DROP INDEX unique_child_profile_name_per_parent');
        } catch (\Exception $e) {
            // Index might not exist
        }

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('profile_name', 'username');
        });

        DB::statement('ALTER TABLE users ADD UNIQUE KEY unique_child_username_per_parent (parent_id, username)');
    }
};
