<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Update all profile_picture filenames to replace spaces with underscores
     */
    public function up(): void
    {
        // Update all users with profile_picture values that contain spaces
        DB::table('users')
            ->whereNotNull('profile_picture')
            ->where('profile_picture', 'LIKE', '% %')
            ->get()
            ->each(function ($user) {
                $newFilename = str_replace(' ', '_', $user->profile_picture);
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['profile_picture' => $newFilename]);
            });
    }

    /**
     * Reverse the migrations.
     * Replace underscores back with spaces (for rollback)
     */
    public function down(): void
    {
        // Update all users with profile_picture values that contain underscores
        DB::table('users')
            ->whereNotNull('profile_picture')
            ->where('profile_picture', 'LIKE', '%_%')
            ->get()
            ->each(function ($user) {
                $newFilename = str_replace('_', ' ', $user->profile_picture);
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['profile_picture' => $newFilename]);
            });
    }
};
