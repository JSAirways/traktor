<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add viewing session fields to device_registrations table.
     * This allows viewing sessions to be stored in device registration instead of Laravel session,
     * avoiding conflicts with session regeneration (e.g., during admin login).
     */
    public function up(): void
    {
        Schema::table('device_registrations', function (Blueprint $table) {
            $table->string('current_viewing_slug', 64)->nullable()->after('is_active');
            $table->timestamp('viewing_validated_at')->nullable()->after('current_viewing_slug');
            $table->timestamp('viewing_expires_at')->nullable()->after('viewing_validated_at');
            
            // Index for faster lookups
            $table->index('current_viewing_slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_registrations', function (Blueprint $table) {
            $table->dropIndex(['current_viewing_slug']);
            $table->dropColumn(['current_viewing_slug', 'viewing_validated_at', 'viewing_expires_at']);
        });
    }
};
