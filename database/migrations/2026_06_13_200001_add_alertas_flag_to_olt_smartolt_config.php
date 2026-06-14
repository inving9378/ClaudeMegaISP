<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('olt_smartolt_config', function (Blueprint $table) {
            // Flag global de alertas PON — false por defecto, activar manualmente.
            // Mientras esté en false, el servicio solo loguea (dry-run automático).
            $table->boolean('alertas_olt_activas')->default(false)->after('activa');
            // Destino de alertas: rol a notificar (null = solo admin/DESARROLLADOR)
            $table->string('alertas_rol_destino')->nullable()->after('alertas_olt_activas');
        });
    }

    public function down(): void
    {
        Schema::table('olt_smartolt_config', function (Blueprint $table) {
            $table->dropColumn(['alertas_olt_activas', 'alertas_rol_destino']);
        });
    }
};
