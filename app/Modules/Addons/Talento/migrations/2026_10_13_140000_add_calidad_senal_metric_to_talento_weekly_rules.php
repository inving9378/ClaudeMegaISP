<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extiende el motor de reglas de desempeño semanal con una métrica de CALIDAD
 * de señal (dBm) independiente de la de cantidad — pedido directo de Irving
 * (2026-10-13): "si un técnico hace menos instalaciones de las que debe, se
 * penaliza... si la calidad de la fibra da más ruido del que pongo, también
 * se penaliza... si se mantiene limpia, se da un bono" — dos ejes de negocio
 * independientes sobre el MISMO colaborador, cada uno con su propio piso/
 * techo/bono/penalización, configurables como regla igual que cantidad.
 *
 * Esto rompe un supuesto de la Fase 2: "una regla activa por combinación
 * exacta de roles" asumía una sola regla por colaborador por semana. Con
 * calidad como eje aparte, un técnico necesita poder tener AMBAS reglas
 * activas (cantidad Y calidad) a la vez → la unicidad pasa de "un rol, una
 * regla" a "un rol, una regla POR metric_type", y una evaluación semanal dej
 * a de ser "una fila por colaborador/semana" para ser "una fila por
 * colaborador/semana/regla".
 */
return new class extends Migration
{
    public function up(): void
    {
        // Cada paso se guarda idempotente — MySQL hace commit por-sentencia en
        // DDL (ALTER TABLE no es transaccional), así que un up() que truena a
        // medias deja pasos previos ya aplicados pero la migración SIN marcar
        // "Ran" -> un reintento repetiría esos pasos y tronaría en "ya existe".
        // Pasó exactamente eso corriendo esta migración (los dos primeros
        // pasos quedaron aplicados, el resto no) -- de ahí el guard.
        if (! Schema::hasColumn('talento_weekly_rules', 'max_loss_db')) {
            DB::statement("ALTER TABLE talento_weekly_rules MODIFY COLUMN metric_type ENUM('ventas','tecnico','mostrador','calidad_senal') NOT NULL DEFAULT 'ventas'");

            Schema::table('talento_weekly_rules', function (Blueprint $table) {
                // Umbral de pérdida de señal (dB) PROPIO de esta regla — a propósito
                // NO reusa el setting global de HealthBonusService
                // (talento_health_bonus_max_loss_db): ese es un bono automático
                // por-orden ya existente y separado; este es el umbral que Irving
                // "pone" al definir la regla semanal, puede ser un número distinto.
                $table->decimal('max_loss_db', 4, 2)->nullable()->after('metric_type');
            });
        }

        // La evaluación deja de ser "una por colaborador/semana" (ahora puede
        // haber una de cantidad Y una de calidad en la misma semana) -> la
        // unicidad debe incluir rule_id. MySQL no fuerza unicidad entre NULLs
        // (caso borde: cero reglas activas en todo el sistema, fila 'no_rule'
        // con rule_id=NULL) -- aceptado a propósito, el propio servicio sigue
        // comprobando existencia en código antes de insertar (ver
        // TalentoWeeklyRuleEvaluationService::evaluateColaborador()).
        $indexes = collect(DB::select('SHOW INDEX FROM talento_weekly_rule_evaluations'))->pluck('Key_name')->unique();
        if ($indexes->contains('talento_weekly_rule_evaluations_colaborador_id_week_start_unique')) {
            Schema::table('talento_weekly_rule_evaluations', function (Blueprint $table) {
                $table->dropUnique('talento_weekly_rule_evaluations_colaborador_id_week_start_unique');
            });
        }
        if (! $indexes->contains('talento_weekly_rule_evaluations_colab_week_rule_unique')) {
            Schema::table('talento_weekly_rule_evaluations', function (Blueprint $table) {
                $table->unique(['colaborador_id', 'week_start', 'rule_id'], 'talento_weekly_rule_evaluations_colab_week_rule_unique');
            });
        }

        $outcomeType = DB::select("SHOW COLUMNS FROM talento_weekly_rule_evaluations WHERE Field='outcome'")[0]->Type;
        if (! str_contains($outcomeType, 'sin_datos')) {
            DB::statement("ALTER TABLE talento_weekly_rule_evaluations MODIFY COLUMN outcome ENUM('penalty_pending','penalty_applied','bonus','meets_quota','no_rule','sin_datos') NOT NULL");
        }
    }

    public function down(): void
    {
        Schema::table('talento_weekly_rule_evaluations', function (Blueprint $table) {
            $table->dropUnique('talento_weekly_rule_evaluations_colab_week_rule_unique');
        });
        Schema::table('talento_weekly_rule_evaluations', function (Blueprint $table) {
            $table->unique(['colaborador_id', 'week_start'], 'talento_weekly_rule_evaluations_colaborador_id_week_start_unique');
        });
        DB::statement("ALTER TABLE talento_weekly_rule_evaluations MODIFY COLUMN outcome ENUM('penalty_pending','penalty_applied','bonus','meets_quota','no_rule') NOT NULL");

        Schema::table('talento_weekly_rules', function (Blueprint $table) {
            $table->dropColumn('max_loss_db');
        });
        DB::statement("ALTER TABLE talento_weekly_rules MODIFY COLUMN metric_type ENUM('ventas','tecnico','mostrador') NOT NULL DEFAULT 'ventas'");
    }
};
