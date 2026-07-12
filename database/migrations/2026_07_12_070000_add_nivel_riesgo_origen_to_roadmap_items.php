<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Circuito #260 — quién fijó el `nivel_riesgo` vigente del item: 'interno' (Claude Code /
 * Irving, clasificación de confianza) o 'externo' (Claude Cowork vía roadmap-externo/MCP).
 * Cierra el hueco donde un item SIN clasificar (nivel_riesgo null, el menos restrictivo)
 * podía ser subido a 'A' por la vía externa y en la misma/otra llamada quedar
 * `aprobado_claude` — el guard de monotonía solo impedía DEGRADAR, no fijar el nivel inicial.
 * Default 'interno' (aditivo, no rompe items existentes clasificados antes de este campo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $t) {
            if (! Schema::hasColumn('roadmap_items', 'nivel_riesgo_origen')) {
                $t->enum('nivel_riesgo_origen', ['interno', 'externo'])
                    ->default('interno')
                    ->after('nivel_riesgo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $t) {
            if (Schema::hasColumn('roadmap_items', 'nivel_riesgo_origen')) {
                $t->dropColumn('nivel_riesgo_origen');
            }
        });
    }
};
