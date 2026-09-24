<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MegaVoz Fase 3 — cola "Atención a Clientes".
 *
 * Un grupo de timbrado normal solo timbra (Dial paralelo). Uno con
 * `es_cola=true` ADEMÁS se provisiona como cola real de Asterisk
 * (app_queue, tablas queues/queue_members) — así se puede saber en
 * cualquier momento, sin haber contestado la llamada, cuántos agentes
 * están libres (QUEUE_MEMBER() en el dialplan — la "regla de oro").
 *
 * Aditiva y con default false: ningún grupo existente cambia de
 * comportamiento hasta que se marque explícitamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voip_grupos_timbrado', function (Blueprint $table) {
            $table->boolean('es_cola')->default(false)->after('estrategia');
        });
    }

    public function down(): void
    {
        Schema::table('voip_grupos_timbrado', function (Blueprint $table) {
            $table->dropColumn('es_cola');
        });
    }
};
