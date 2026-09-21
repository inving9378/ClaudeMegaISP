<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap interno de Talento — Fase 11 ("WarrantyWindowService::refreshWindow()
 * para tasks, Capa 6.1"). `talento_responsibility_windows` ya recibió `tarea_id`
 * (nullable, FK a tasks) en la migración 2026_06_08_210956, pero quedó fuera de
 * 2026_06_08_214726 (esa solo tocó columnas literalmente llamadas `work_order_id`;
 * aquí la columna se llama `source_work_order_id`) — así que `source_work_order_id`
 * siguió NOT NULL y una ventana de garantía originada en una task (sin fila en
 * talento_work_orders) no se podía insertar. Aditivo: solo relaja el constraint,
 * no toca datos ni el FK existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        $fkRow = DB::selectOne(
            "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'talento_responsibility_windows'
               AND COLUMN_NAME = 'source_work_order_id'
               AND REFERENCED_TABLE_NAME = 'talento_work_orders'"
        );

        Schema::table('talento_responsibility_windows', function (Blueprint $table) use ($fkRow) {
            if ($fkRow) {
                $table->dropForeign($fkRow->CONSTRAINT_NAME);
            }
            $table->unsignedBigInteger('source_work_order_id')->nullable()->change();
        });

        Schema::table('talento_responsibility_windows', function (Blueprint $table) {
            $table->foreign('source_work_order_id', 'fk_trw_source_wo')
                ->references('id')->on('talento_work_orders')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        // Forward-only: revertir a NOT NULL rompería filas ya originadas en tasks.
    }
};
