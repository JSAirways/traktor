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
            // Drop existing unique constraint on email
            $table->dropUnique(['email']);
            
            // Make email nullable (allows NULL for child accounts)
            $table->string('email')->nullable()->change();
            
            // Re-add unique constraint (allows multiple NULLs but unique non-NULL values)
            $table->unique('email');
        });
        
        // Now update existing child accounts to have NULL email instead of dummy emails
        DB::table('users')
            ->whereNotNull('parent_id')
            ->where('email', 'like', '%@child.local')
            ->update(['email' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop unique constraint
            $table->dropUnique(['email']);
            
            // Make email NOT NULL again
            $table->string('email')->nullable(false)->change();
            
            // Re-add unique constraint
            $table->unique('email');
        });
        
        // Restore dummy emails for child accounts (optional - for rollback)
        // This would require storing original emails, which we don't do
    }
};
