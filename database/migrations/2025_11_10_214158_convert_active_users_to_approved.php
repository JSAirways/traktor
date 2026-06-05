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
        // First, convert all 'active' users to 'approved'
        DB::table('users')
            ->where('account_status', 'active')
            ->update([
                'account_status' => 'approved',
                'approved_at' => DB::raw('COALESCE(approved_at, NOW())'),
            ]);

        // Modify the enum to remove 'active' option
        // MySQL requires dropping and recreating the column to modify enum
        DB::statement("ALTER TABLE users MODIFY COLUMN account_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore 'active' option to enum
        DB::statement("ALTER TABLE users MODIFY COLUMN account_status ENUM('pending', 'approved', 'rejected', 'active') DEFAULT 'pending'");
        
        // Note: We can't automatically convert back to 'active' since we don't know which were originally active
        // Users will need to be manually updated if rollback is needed
    }
};
