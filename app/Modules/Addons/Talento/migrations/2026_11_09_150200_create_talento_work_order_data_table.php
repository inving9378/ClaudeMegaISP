<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #1200, Fase A3 — valores capturados por orden con plantilla.
 *
 * - `schema_snapshot`: copia de los campos de la plantilla al crear la orden. Si la plantilla
 *   se edita después, la orden se sigue mostrando con los campos con que nació.
 * - `values`: {key: valor} ya normalizados por Support\EsquemaCamposOrden.
 * - `template_id`: de qué plantilla salió (solo trazabilidad; puede quedar en null).
 *
 * Una fila por tarea (tasks.id).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('talento_work_order_data')) {
            return;
        }

        Schema::create('talento_work_order_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tarea_id')->unique('twod_tarea_unique');
            $table->unsignedBigInteger('template_id')->nullable()->index('twod_template_idx');
            $table->json('schema_snapshot');
            $table->json('values');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('tarea_id', 'twod_tarea_fk')
                ->references('id')->on('tasks')
                ->cascadeOnDelete();
            $table->foreign('template_id', 'twod_template_fk')
                ->references('id')->on('talento_work_order_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_work_order_data');
    }
};
