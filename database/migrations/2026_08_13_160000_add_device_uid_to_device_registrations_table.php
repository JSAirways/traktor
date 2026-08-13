<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Clean-sheet cutover: wipes existing device registrations so durable
     * device_uid can be required without backfill.
     *
     * WARNING: Deletes all rows in device_registrations and device_child_visibility.
     * Safe for tester cutover; do not apply against production without an explicit wipe.
     */
    public function up(): void
    {
        // Wipe device data (tester clean sheet). Child visibility first (FK).
        if (Schema::hasTable('device_child_visibility')) {
            DB::table('device_child_visibility')->delete();
        }
        if (Schema::hasTable('device_registrations')) {
            DB::table('device_registrations')->delete();
        }

        Schema::table('device_registrations', function (Blueprint $table) {
            if (!Schema::hasColumn('device_registrations', 'device_uid')) {
                $table->uuid('device_uid')->after('parent_user_id');
            }
        });

        // Drop fingerprint uniqueness if present (may already be gone on retry)
        $indexes = collect(DB::select('SHOW INDEX FROM device_registrations'))
            ->pluck('Key_name')
            ->unique()
            ->all();

        if (in_array('device_registrations_parent_fingerprint_unique', $indexes, true)) {
            Schema::table('device_registrations', function (Blueprint $table) {
                $table->dropUnique('device_registrations_parent_fingerprint_unique');
            });
        }

        if (!in_array('device_registrations_parent_device_uid_unique', $indexes, true)) {
            Schema::table('device_registrations', function (Blueprint $table) {
                $table->unique(['parent_user_id', 'device_uid'], 'device_registrations_parent_device_uid_unique');
            });
        }

        if (!in_array('device_registrations_device_uid_index', $indexes, true)) {
            Schema::table('device_registrations', function (Blueprint $table) {
                $table->index('device_uid', 'device_registrations_device_uid_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $indexes = collect(DB::select('SHOW INDEX FROM device_registrations'))
            ->pluck('Key_name')
            ->unique()
            ->all();

        Schema::table('device_registrations', function (Blueprint $table) use ($indexes) {
            if (in_array('device_registrations_parent_device_uid_unique', $indexes, true)) {
                $table->dropUnique('device_registrations_parent_device_uid_unique');
            }
            if (in_array('device_registrations_device_uid_index', $indexes, true)) {
                $table->dropIndex('device_registrations_device_uid_index');
            }
            if (Schema::hasColumn('device_registrations', 'device_uid')) {
                $table->dropColumn('device_uid');
            }
        });
    }
};
