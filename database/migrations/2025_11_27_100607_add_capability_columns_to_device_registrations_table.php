<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('device_registrations', function (Blueprint $table) {
            $table->json('capabilities')->nullable()->after('screen_resolution');
            $table->string('capability_hash', 128)->nullable()->after('capabilities')->index();
            $table->timestamp('token_expires_at')->nullable()->after('last_used_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_registrations', function (Blueprint $table) {
            $table->dropColumn(['capabilities', 'capability_hash', 'token_expires_at']);
        });
    }
};
