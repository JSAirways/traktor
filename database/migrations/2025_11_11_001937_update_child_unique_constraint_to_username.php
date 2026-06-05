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
        // Drop the old unique constraint on (parent_id, name/slug)
        // The column was renamed from name to slug, so the constraint name should still exist
        try {
            DB::statement('ALTER TABLE users DROP INDEX unique_child_name_per_parent');
        } catch (\Exception $e) {
            // Constraint might not exist or have different name, continue
        }
        
        // Add new composite unique constraint on (parent_id, username) for children
        // This ensures each parent can only have one child with a given username
        // Note: MySQL allows multiple NULLs in unique constraints, so parents (parent_id = NULL) are not affected
        DB::statement('ALTER TABLE users ADD UNIQUE KEY unique_child_username_per_parent (parent_id, username)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            try {
                $table->dropUnique('unique_child_username_per_parent');
            } catch (\Exception $e) {
                // Constraint might not exist
            }
        });
        
        // Restore the old constraint on slug (which was name)
        DB::statement('ALTER TABLE users ADD UNIQUE KEY unique_child_name_per_parent (parent_id, slug)');
    }
};

