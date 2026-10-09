<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('monitoring_cases', 'notify_email')) {
            return; // idempotente
        }
        Schema::table('monitoring_cases', function (Blueprint $table) {
            $table->string('notify_email')->nullable()->after('is_demo');
            $table->string('notify_whatsapp', 32)->nullable()->after('notify_email');
        });
    }

    public function down(): void
    {
        // Columnas PROPIAS del módulo sobre su propia tabla: seguro retirarlas.
        Schema::table('monitoring_cases', function (Blueprint $table) {
            $table->dropColumn(['notify_email', 'notify_whatsapp']);
        });
    }
};
