<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visibilidad por rol dentro del "Manual de Usuario" (addon-manual) — mismo
 * mecanismo que ya tiene el "Manual Operativo de Meganet" (addon-empresa),
 * ver App\Support\Manual\HasManualRoleVisibility. null/vacío = visible para
 * cualquiera que pueda ver el manual (sin cambio para el contenido
 * auto-generado ya existente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_sections', function (Blueprint $table) {
            $table->json('visible_roles')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('manual_sections', function (Blueprint $table) {
            $table->dropColumn('visible_roles');
        });
    }
};
