<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parental_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('parental_accounts')->cascadeOnDelete();
            $table->foreignId('profile_id')->nullable()->constrained('parental_profiles')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('parental_devices')->nullOnDelete();
            $table->enum('type', [
                'uninstall_attempt',
                'geofence_exit',
                'blocked_content',
                'low_battery',
                'device_offline',
            ]);
            $table->text('detail')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'read_at']);
            $table->index(['profile_id', 'type']);
        });
    }

    public function down(): void { Schema::dropIfExists('parental_alerts'); }
};
