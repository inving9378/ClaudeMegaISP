<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #988 — Promedios de 5 minutos por servicio, que se conservan 13 meses (el detalle por minuto solo
 * 30 días; ver PurgaMetricas), e índice por minuto para que promediar y purgar no recorran toda la tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cliente_metricas_5min')) {
            Schema::create('cliente_metricas_5min', function (Blueprint $table) {
                $table->id();
                $table->foreignId('servicio_id')->constrained('client_internet_services')->cascadeOnDelete();
                $table->foreignId('cliente_id')->constrained('clients')->cascadeOnDelete();
                $table->dateTime('ts')->comment('Inicio del bloque de 5 minutos');
                $table->unsignedTinyInteger('muestras')->comment('Minutos con lectura en el bloque');
                $table->unsignedBigInteger('bajada_bps')->nullable()->comment('Promedio');
                $table->unsignedBigInteger('bajada_bps_max')->nullable();
                $table->unsignedBigInteger('subida_bps')->nullable()->comment('Promedio');
                $table->unsignedBigInteger('subida_bps_max')->nullable();
                $table->decimal('lat_avg_ms', 9, 3)->nullable();
                $table->decimal('lat_max_ms', 9, 3)->nullable();
                $table->decimal('loss_pct', 5, 2)->nullable()->comment('Promedio');
                $table->decimal('loss_max_pct', 5, 2)->nullable();
                $table->unsignedTinyInteger('minutos_en_linea')->default(0);
                $table->unsignedTinyInteger('minutos_degradado')->default(0);
                $table->unsignedTinyInteger('minutos_sin_respuesta')->default(0);
                $table->string('origen', 16)->default('mikrotik');
                $table->unique(['servicio_id', 'ts']);
                $table->index('ts');
            });
        }

        foreach (['cliente_metricas', 'cliente_metricas_ping'] as $tabla) {
            if (DB::select("SHOW INDEX FROM `{$tabla}` WHERE Key_name = ?", ["{$tabla}_ts_index"]) === []) {
                Schema::table($tabla, fn (Blueprint $table) => $table->index('ts'));
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_metricas_5min');
        foreach (['cliente_metricas', 'cliente_metricas_ping'] as $tabla) {
            if (DB::select("SHOW INDEX FROM `{$tabla}` WHERE Key_name = ?", ["{$tabla}_ts_index"]) !== []) {
                Schema::table($tabla, fn (Blueprint $table) => $table->dropIndex(['ts']));
            }
        }
    }
};
