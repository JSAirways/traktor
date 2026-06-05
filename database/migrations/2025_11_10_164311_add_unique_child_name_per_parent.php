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
        // Add composite unique constraint on (parent_id, name) for children
        // This ensures each parent can only have one child with a given name
        // Note: MySQL allows multiple NULLs in unique constraints, so parents (parent_id = NULL) are not affected
        DB::statement('ALTER TABLE users ADD UNIQUE KEY unique_child_name_per_parent (parent_id, name)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('unique_child_name_per_parent');
        });
    }
};
