<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOTOR DE AUDITORÍA CONTINUA (#559) — Fase 4 del Supervisor, prerequisito (item #232).
 *
 * Decisiones ratificadas por Irving (log del item #232, 2026-08-28):
 * 1. `tipo` es varchar(20) + constante en el modelo, NO enum — agregar un valor nuevo es dato,
 *    no un ALTER sobre esta tabla de 91 columnas (mismo criterio que `inventory_item_types.categoria`).
 * 2. Las 223 filas existentes quedan en `manual` — todo lo que existe hoy lo creó o lo pidió un
 *    humano, ninguna nació de una auditoría automática.
 * 3. NO se crea `hallazgo_firma`: ya existe `auditor_fingerprint` (migración
 *    2026_08_08_190000) cumpliendo ese rol de huella de dedup del auditor. Dos columnas de huella
 *    conviviendo es la clase de segunda definición que este sistema ya pagó cara varias veces.
 *
 * `auditoria_ciclo` sí es nueva: un entero sin signo, nullable, sin default — identifica en qué
 * corrida de `AuditorService::ciclo()` nació el item (NULL = no lo generó el auditor, igual criterio
 * que `auditor_fingerprint`). Hoy `ciclo()` no tiene un número de corrida — eso es cablear el
 * consumidor, que es la Fase 4 del Supervisor y NO el alcance de este item.
 *
 * Aditiva: sin JSON/TEXT (evita el defecto conocido de esta tabla, error 1038 por filesort con
 * sort_buffer chico — `roadmap_select_star_sort_1038`). Sin tocar ninguna consulta existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roadmap_items')) {
            return;
        }

        if (! Schema::hasColumn('roadmap_items', 'tipo')) {
            Schema::table('roadmap_items', function (Blueprint $table) {
                $table->string('tipo', 20)->default('manual')->after('auditor_fingerprint');
            });
        }

        if (! Schema::hasColumn('roadmap_items', 'auditoria_ciclo')) {
            Schema::table('roadmap_items', function (Blueprint $table) {
                $table->unsignedInteger('auditoria_ciclo')->nullable()->after('tipo');
            });
        }

        // Backfill explícito (decisión 2): aunque el DEFAULT ya cubre altas futuras, deja las 223
        // filas existentes en 'manual' sin depender de que ninguna llegara con NULL antes del ALTER.
        DB::table('roadmap_items')->whereNull('tipo')->update(['tipo' => 'manual']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('roadmap_items')) {
            return;
        }

        if (Schema::hasColumn('roadmap_items', 'auditoria_ciclo')) {
            Schema::table('roadmap_items', function (Blueprint $table) {
                $table->dropColumn('auditoria_ciclo');
            });
        }

        if (Schema::hasColumn('roadmap_items', 'tipo')) {
            Schema::table('roadmap_items', function (Blueprint $table) {
                $table->dropColumn('tipo');
            });
        }
    }
};
