<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ENTREGA 1 del panel de configuración de la Torre — el sustrato.
 *
 * POR QUÉ EXISTE. Las reglas que deciden cuánto avanza el circuito sin Irving vivían repartidas
 * entre `config/circuito.php`, convenciones y la cabeza de quien redactaba cada item. Peor: había
 * **cuatro topes de nivel distintos** y uno de ellos —`circuito.autopilot.max_nivel`— gobernaba de
 * hecho a TODOS los actores desde `RoadmapItem::scopeDespachable`, con un nombre que decía otra cosa.
 *
 * `torre_config` es la fuente única del **techo global**. Los sub-techos por actor
 * (`autopilot`, `thomas.mecanico`, `thomas.ya_decidido`) sobreviven en `config/circuito.php` con su
 * significado literal, y el nivel efectivo de cada uno es `min(techo_global, sub_techo)`.
 *
 * ⚠️ VALORES INICIALES = LO QUE EL CIRCUITO HACE HOY, no lo que debería hacer.
 *   · `nivel_automatizacion = 'autonomo'` (techo C), porque `autopilot.max_nivel` vale C desde el
 *     2026-08-04 (item #507, decisión explícita de Irving).
 *   · `auditor_max_por_corrida = 10` y `auditor_cooldown_min = 15`, los reales de
 *     `circuito.auditor.cap_por_ciclo` / `min_intervalo_minutos`.
 * Construir el tablero y cambiar el comportamiento a la vez haría imposible saber cuál de los dos
 * causó lo que pase después. Ajustarlo es un clic de Irving, en otro momento.
 *
 * Los campos de motores que HOY NO EXISTEN en el repo (heartbeat del auditor, ejes, veto de
 * `rechazado`, canal de respuesta) **no se crean**: un control que no gobierna nada enseña a
 * desconfiar del tablero entero. Entran en la Entrega 2, cuando exista qué gobernar.
 *
 * Migración ADITIVA. Nada de `migrate:fresh`: la base tiene datos reales.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('torre_config')) {
            Schema::create('torre_config', function (Blueprint $table) {
                $table->id();

                // Techo GLOBAL de automatización. manual | estandar | asistido | autonomo.
                // varchar y no enum: agregar un nivel no debe pedir un ALTER (misma decisión que
                // `inventory_item_types.categoria`, item #572).
                $table->string('nivel_automatizacion', 12)->default('estandar');

                // Auditor — SOLO los dos parámetros que hoy gobiernan algo real.
                $table->boolean('auditor_activo')->default(true);
                $table->unsignedTinyInteger('auditor_max_por_corrida')->default(10);
                $table->unsignedSmallInteger('auditor_cooldown_min')->default(15);

                $table->timestamps();
            });
        }

        // Fila ÚNICA (singleton). `updateOrInsert` sobre id=1 para que re-correr no duplique.
        DB::table('torre_config')->updateOrInsert(
            ['id' => 1],
            [
                // = techo C = lo que el circuito hace HOY (autopilot.max_nivel = C, #507).
                'nivel_automatizacion'    => 'autonomo',
                'auditor_activo'          => (bool) config('circuito.auditor.enabled', true),
                'auditor_max_por_corrida' => (int) config('circuito.auditor.cap_por_ciclo', 10),
                'auditor_cooldown_min'    => (int) config('circuito.auditor.min_intervalo_minutos', 15),
                'created_at'              => now(),
                'updated_at'              => now(),
            ]
        );

        if (! Schema::hasColumn('roadmap_items', 'automatizacion_override')) {
            Schema::table('roadmap_items', function (Blueprint $table) {
                // hereda | manual | auto. Default 'hereda' = sigue la política global, que es el
                // comportamiento de todos los items existentes. Nadie cambia de régimen al migrar.
                $table->string('automatizacion_override', 10)->default('hereda')->after('nivel_riesgo_origen');
                $table->index('automatizacion_override');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('roadmap_items', 'automatizacion_override')) {
            Schema::table('roadmap_items', function (Blueprint $table) {
                $table->dropIndex(['automatizacion_override']);
                $table->dropColumn('automatizacion_override');
            });
        }
        Schema::dropIfExists('torre_config');
    }
};
