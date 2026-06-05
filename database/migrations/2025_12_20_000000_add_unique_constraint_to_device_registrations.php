<?php

declare(strict_types=1);

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
        // First, clean up any existing duplicates before adding the constraint
        // Keep the most recent device for each (parent_user_id, device_fingerprint) pair
        $duplicates = DB::table('device_registrations')
            ->select('parent_user_id', 'device_fingerprint', DB::raw('COUNT(*) as count'))
            ->whereNotNull('device_fingerprint')
            ->groupBy('parent_user_id', 'device_fingerprint')
            ->having('count', '>', 1)
            ->get();

        foreach ($duplicates as $duplicate) {
            // Get all devices with this fingerprint for this user, ordered by most recent
            $devices = DB::table('device_registrations')
                ->where('parent_user_id', $duplicate->parent_user_id)
                ->where('device_fingerprint', $duplicate->device_fingerprint)
                ->orderBy('last_used_at', 'desc')
                ->orderBy('registered_at', 'desc')
                ->orderBy('id', 'desc')
                ->get();

            // Keep the first (most recent) device, deactivate the rest
            if ($devices->count() > 1) {
                $keepDevice = $devices->first();
                $removeDevices = $devices->skip(1);

                foreach ($removeDevices as $removeDevice) {
                    // Deactivate duplicate devices instead of deleting (preserve data)
                    DB::table('device_registrations')
                        ->where('id', $removeDevice->id)
                        ->update([
                            'is_active' => false,
                            'device_fingerprint' => null, // Clear fingerprint to allow constraint
                        ]);
                }
            }
        }

        Schema::table('device_registrations', function (Blueprint $table) {
            // Add unique constraint on (parent_user_id, device_fingerprint)
            // Note: MySQL allows multiple NULL values in a unique index, so devices without fingerprints
            // can still be created, but devices with the same fingerprint for the same user will be unique
            $table->unique(['parent_user_id', 'device_fingerprint'], 'device_registrations_parent_fingerprint_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_registrations', function (Blueprint $table) {
            $table->dropUnique('device_registrations_parent_fingerprint_unique');
        });
    }
};

