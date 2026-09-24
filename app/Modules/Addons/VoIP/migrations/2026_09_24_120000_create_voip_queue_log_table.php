<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MegaVoz Fase 5 — copia estructurada de `queue_log` (el log nativo de
 * app_queue, `/var/log/asterisk/queue_log`, formato
 * `timestamp|callid|queuename|agent|event|data1|data2|...`).
 *
 * A diferencia de `voip_llamadas` (CDR, escrito DIRECTO por Asterisk vía
 * `cdr_adaptive_odbc`), esta tabla la llena un comando propio de MegaISP
 * (`megavoz:importar-queue-log`) que TAIL-ea el archivo — por eso vive en la
 * conexión del app (`megaisp`), no en `asterisk_rt`: Asterisk nunca escribe
 * aquí directo, solo el importador la produce.
 *
 * `queue_log` es la única fuente confiable para KPIs POR AGENTE (quién
 * contestó, cuánto esperó el cliente, si abandonó) — el CDR de `voip_llamadas`
 * ve la llamada completa pero no desglosa qué pasó DENTRO de la cola cuando
 * timbran varios miembros a la vez (ringall).
 *
 * ADITIVA.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('voip_queue_log')) {
            return;
        }

        Schema::create('voip_queue_log', function (Blueprint $table) {
            $table->id();
            $table->timestamp('ts')->index();
            $table->string('callid', 60)->nullable()->index();
            $table->string('queuename', 80)->nullable()->index();
            $table->string('agent', 120)->nullable()->index();
            $table->string('event', 40)->index();
            $table->string('data1', 200)->nullable();
            $table->string('data2', 200)->nullable();
            $table->string('data3', 200)->nullable();
            $table->string('data4', 200)->nullable();
            $table->string('data5', 200)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['queuename', 'event', 'ts']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voip_queue_log');
    }
};
