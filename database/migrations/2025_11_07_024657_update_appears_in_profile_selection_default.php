<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Change default value to true for appears_in_profile_selection
            $table->boolean('appears_in_profile_selection')->default(true)->change();
        });
        
        // Update existing parent users (where parent_id IS NULL) to true
        DB::table('users')
            ->whereNull('parent_id')
            ->update(['appears_in_profile_selection' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Revert default value back to false
            $table->boolean('appears_in_profile_selection')->default(false)->change();
        });
        
        // Note: We don't revert existing users to false in down() to avoid data loss
    }
};
