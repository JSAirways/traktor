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
            $table->text('user_agent')->nullable()->after('device_fingerprint');
            $table->string('screen_resolution', 50)->nullable()->after('user_agent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_registrations', function (Blueprint $table) {
            $table->dropColumn(['user_agent', 'screen_resolution']);
        });
    }
};
