<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #1016 (sub-item de #1004 §4) — infraestructura del "minero de bitácora".
 *
 * Tabla genérica y reusable para CUALQUIER señal futura de log-eventos (no solo
 * "intención abandonada"): `tipo` distingue la señal, `fuente` de dónde se minó
 * (v1: 'activity_log'). SOLO detección — nadie escribe aquí una acción, es un
 * listado read-only para revisión humana (clase "producto" del circuito).
 *
 * Modelo plano SIN LogsActivity/BaseModel a propósito: si extendiera BaseModel,
 * cada señal insertada generaría su propia fila en `activity_log`, que el propio
 * minero volvería a leer en la siguiente corrida (ruido auto-alimentado sobre su
 * propia tabla). Mismo patrón que FleetDeviceEvent/FleetPosition (alto volumen,
 * sin auditoría de sí mismas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria_senales', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 64)->index();
            $table->string('fuente', 64)->default('activity_log')->index();
            $table->timestamp('ocurrido_en')->index();
            $table->json('payload')->nullable();
            $table->string('dedupe_key', 64)->unique();
            $table->boolean('revisado')->default(false);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria_senales');
    }
};
