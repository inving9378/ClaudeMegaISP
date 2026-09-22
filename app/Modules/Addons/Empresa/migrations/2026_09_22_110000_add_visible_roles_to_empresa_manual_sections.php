<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visibilidad por rol dentro del Manual General de la Empresa. Cada sección
 * puede quedar acotada a una lista de roles (Spatie) — null/vacío = visible
 * para cualquiera que pueda ver el manual (comportamiento actual, sin cambio
 * para las secciones ya existentes). super-administrator/DESARROLLADOR/
 * Super Administrador/Administrador/ADMINISTRADOR_COMPLETO siempre ven todo
 * (bypass en el controlador, no aquí), para poder editar el árbol completo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa_manual_sections', function (Blueprint $table) {
            $table->json('visible_roles')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('empresa_manual_sections', function (Blueprint $table) {
            $table->dropColumn('visible_roles');
        });
    }
};
