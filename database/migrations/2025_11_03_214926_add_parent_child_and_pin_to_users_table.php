<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add parent_id for parent-child relationships
            $table->foreignId('parent_id')->nullable()->after('role')->constrained('users')->onDelete('cascade');
            
            // Add view_pin for PIN-based access (stored as hash)
            $table->string('view_pin')->nullable()->after('parent_id');
            
            // Add is_viewable flag to enable/disable viewing page access
            $table->boolean('is_viewable')->default(true)->after('view_pin');
            
            // Add account_status for registration approval workflow
            $table->enum('account_status', ['pending', 'approved', 'rejected', 'active'])->default('pending')->after('is_viewable');
            
            // Add approval tracking fields
            $table->timestamp('approved_at')->nullable()->after('account_status');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->onDelete('set null');
            $table->text('rejection_reason')->nullable()->after('approved_by');
            
            // Add locale for translation support
            $table->string('locale')->nullable()->after('rejection_reason');
            
            // Add index on parent_id for fast lookups
            $table->index('parent_id');
            
            // Add constraint to prevent users from being their own parent
            // This will be handled in application logic as MySQL doesn't support CHECK constraints easily
        });
        
        // Set existing users to 'active' status
        DB::table('users')->update(['account_status' => 'active']);
        
        // Generate random PINs for existing users (if they don't have one)
        $users = DB::table('users')->whereNull('view_pin')->get();
        foreach ($users as $user) {
            // Generate 4-digit PIN
            $pin = str_pad((string)rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            // Hash the PIN
            $hashedPin = Hash::make($pin);
            DB::table('users')->where('id', $user->id)->update(['view_pin' => $hashedPin]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['parent_id']);
            $table->dropColumn([
                'parent_id',
                'view_pin',
                'is_viewable',
                'account_status',
                'approved_at',
                'approved_by',
                'rejection_reason',
                'locale'
            ]);
        });
    }
};
