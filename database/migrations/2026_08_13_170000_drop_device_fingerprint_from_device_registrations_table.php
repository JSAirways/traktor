<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop retired device_fingerprint column and leftover indexes.
     */
    public function up(): void
    {
        if (!Schema::hasTable('device_registrations') || !Schema::hasColumn('device_registrations', 'device_fingerprint')) {
            return;
        }

        $indexes = collect(DB::select('SHOW INDEX FROM device_registrations'))
            ->pluck('Key_name')
            ->unique()
            ->all();

        Schema::table('device_registrations', function (Blueprint $table) use ($indexes) {
            if (in_array('device_registrations_parent_fingerprint_unique', $indexes, true)) {
                $table->dropUnique('device_registrations_parent_fingerprint_unique');
            }
        });

        // Refresh index list after possible unique drop
        $indexes = collect(DB::select('SHOW INDEX FROM device_registrations'))
            ->pluck('Key_name')
            ->unique()
            ->all();

        // Drop composite / single indexes that reference device_fingerprint if still present
        // Laravel default names from earlier migrations
        $fingerprintIndexes = [
            'device_registrations_device_fingerprint_index',
            'device_registrations_parent_user_id_device_fingerprint_index',
        ];

        Schema::table('device_registrations', function (Blueprint $table) use ($indexes, $fingerprintIndexes) {
            foreach ($fingerprintIndexes as $indexName) {
                if (in_array($indexName, $indexes, true)) {
                    $table->dropIndex($indexName);
                }
            }

            $table->dropColumn('device_fingerprint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('device_registrations') || Schema::hasColumn('device_registrations', 'device_fingerprint')) {
            return;
        }

        Schema::table('device_registrations', function (Blueprint $table) {
            $table->string('device_fingerprint', 64)->nullable()->after('device_token');
            $table->index('device_fingerprint');
            $table->index(['parent_user_id', 'device_fingerprint']);
        });
    }
};
