<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Item #121 — decisión de Irving (2026-08-27): marco de compensación para roles NO-técnicos
 * (contabilidad, atención a clientes; mostrador/vendedor ya existían como target_type).
 *
 * Aditivo, sin inventar montos/fórmulas reales (esos los carga Irving/admin luego vía la UI de
 * Talento → Compensación). El motor de liquidación (LiquidationService) sigue leyendo SOLO
 * base_salary/weekly_quota_units — estas columnas nuevas quedan sin consumidor de cálculo todavía
 * (ver sub-item registrado para el motor de comisión-KPI/clawback real).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Nuevos target_type nombrados por el item: contabilidad (accounting), atención a
        // clientes (support). 'counter' (mostrador) y 'seller' (vendedor) ya existían.
        DB::statement("ALTER TABLE talento_compensation_rules MODIFY COLUMN target_type ENUM('technician','seller','counter','all','accounting','support') NOT NULL DEFAULT 'all'");

        Schema::table('talento_compensation_rules', function (Blueprint $table) {
            // Marco decidido (q1): null = modelo legacy de cuota/unidad (técnicos, sin cambio);
            // 'comision_kpi' = sueldo base + variable atada a un KPI del rol.
            $table->string('variable_type', 30)->nullable()->after('weekly_quota_units');
            // Etiqueta libre del KPI que dispara la variable (p.ej. activaciones_netas,
            // recuperado_cobranza, csat_tickets_cerrados) — sin catálogo cerrado todavía.
            $table->string('kpi_key', 60)->nullable()->after('variable_type');
            // Parámetros de la fórmula (porcentaje, tabulador, etc.) — vacío hasta que Irving
            // defina los números reales; NO se inventa ningún valor aquí.
            $table->json('formula_config')->nullable()->after('kpi_key');
            // Vigencia (q4).
            $table->date('valid_from')->nullable()->after('formula_config');
            $table->date('valid_until')->nullable()->after('valid_from');
            // Corte mensual (q2: día 25) — solo aplica cuando period=monthly; nullable porque el
            // modelo legacy semanal no lo usa.
            $table->unsignedTinyInteger('monthly_cutoff_day')->nullable()->after('valid_until');
            // Clawback (q3): días de gracia antes de que una baja de cliente active el clawback,
            // y si además aplica el segundo gatillo ("cobranza no logra cobrar el mes"). Solo
            // configuración — el motor que lo EVALÚE contra datos reales de clientes/cobranza
            // queda fuera de esta vuelta (sub-item registrado).
            $table->unsignedSmallInteger('clawback_days')->nullable()->after('monthly_cutoff_day');
            $table->boolean('clawback_requires_collection')->nullable()->after('clawback_days');
        });
    }

    public function down(): void
    {
        Schema::table('talento_compensation_rules', function (Blueprint $table) {
            $table->dropColumn([
                'variable_type', 'kpi_key', 'formula_config', 'valid_from', 'valid_until',
                'monthly_cutoff_day', 'clawback_days', 'clawback_requires_collection',
            ]);
        });

        DB::statement("ALTER TABLE talento_compensation_rules MODIFY COLUMN target_type ENUM('technician','seller','counter','all') NOT NULL DEFAULT 'all'");
    }
};
