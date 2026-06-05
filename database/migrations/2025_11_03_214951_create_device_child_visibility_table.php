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
        Schema::create('device_child_visibility', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_registration_id')->constrained('device_registrations')->onDelete('cascade');
            $table->foreignId('child_user_id')->constrained('users')->onDelete('cascade');
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
            
                // Ensure unique combination (custom name to avoid MySQL 64-char limit)
                $table->unique(['device_registration_id', 'child_user_id'], 'device_child_visibility_unique');
            
            // Indexes for performance
            $table->index('device_registration_id');
            $table->index('child_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_child_visibility');
    }
};
