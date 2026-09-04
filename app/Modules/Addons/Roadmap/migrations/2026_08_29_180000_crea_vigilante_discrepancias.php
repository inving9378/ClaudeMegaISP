<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #773 (fase 3 de #705) — bitácora de discrepancias declarado-por-supervisor vs
 * proceso-real-del-SO, correlacionadas por PID.
 *
 * POR QUÉ EXISTE: `CompuertasSondaCommand::medirWorkers()` ya medía ambos lados (procesos
 * `artisan queue:work` vivos vs `supervisorctl status`) pero solo como CONTEOS separados — no
 * decía CUÁL programa declarado está vivo o muerto de verdad. El incidente de origen (docblock
 * de esa clase, línea 14) fue exactamente "supervisor decía detenido, el proceso seguía vivo" el
 * 24-ago. Esta tabla es la proyección auditable de esa correlación (q2 del brief aprobado): una
 * fila por discrepancia detectada, abierta mientras persiste (`resuelto_at` null), cerrada sola
 * cuando una corrida posterior de la sonda ya no la ve. Solo lectura desde el punto de vista de
 * consumidores: la escribe únicamente `CompuertasSondaCommand::medirWorkers()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vigilante_discrepancias')) {
            return;
        }

        Schema::create('vigilante_discrepancias', function (Blueprint $table) {
            $table->id();
            $table->string('programa', 190);

            // Texto crudo de la segunda columna de `supervisorctl status` (RUNNING/STOPPED/FATAL/...).
            $table->string('estado_supervisor', 40);

            // 'sin_proceso' (declarado RUNNING sin PID vivo) | 'proceso_vivo' (declarado no-RUNNING
            // con PID vivo).
            $table->string('estado_real', 40);

            $table->string('pids_supervisor', 190)->nullable();
            $table->string('pids_reales', 190)->nullable();

            $table->dateTime('detectado_at');
            $table->dateTime('resuelto_at')->nullable();

            $table->index('programa');
            $table->index('resuelto_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vigilante_discrepancias');
    }
};
