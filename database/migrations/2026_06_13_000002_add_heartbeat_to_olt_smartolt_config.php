<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('olt_smartolt_config', function (Blueprint $table) {
            $table->timestamp('last_sync_ok_at')->nullable()->after('activa');
            $table->timestamp('last_sync_error_at')->nullable()->after('last_sync_ok_at');
            $table->timestamp('connected_since')->nullable()->after('last_sync_error_at');
            $table->text('last_error_message')->nullable()->after('connected_since');
            $table->unsignedInteger('last_olt_count')->nullable()->after('last_error_message');
        });
    }

    public function down(): void
    {
        Schema::table('olt_smartolt_config', function (Blueprint $table) {
            $table->dropColumn([
                'last_sync_ok_at', 'last_sync_error_at', 'connected_since',
                'last_error_message', 'last_olt_count',
            ]);
        });
    }
};
