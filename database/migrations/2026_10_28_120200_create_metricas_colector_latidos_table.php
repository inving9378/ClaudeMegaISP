<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Latido del colector de tráfico de dedicados (item #988, Fase 2): una fila por corrida de
 * metricas:colectar. Sirve para distinguir en la gráfica «sin datos» (no hubo corrida en ese
 * minuto, o su router no contestó) de «cliente caído» (hubo lectura y la interfaz estaba abajo).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('metricas_colector_latidos')) {
            return;
        }

        Schema::create('metricas_colector_latidos', function (Blueprint $table) {
            $table->id();
            $table->dateTime('ts')->index()->comment('Minuto de la corrida');
            $table->string('driver', 16);
            $table->unsignedInteger('duracion_ms')->default(0);
            $table->unsignedSmallInteger('routers_ok')->default(0);
            $table->unsignedSmallInteger('routers_error')->default(0);
            $table->unsignedInteger('clientes_ok')->default(0);
            $table->unsignedInteger('clientes_error')->default(0);
            $table->unsignedInteger('clientes_incompletos')->default(0)->comment('Dedicados sin router o interfaz');
            $table->json('detalle')->nullable()->comment('Por router: ok o el error');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metricas_colector_latidos');
    }
};
