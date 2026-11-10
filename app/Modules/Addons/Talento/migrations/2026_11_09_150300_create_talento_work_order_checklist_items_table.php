<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #1200, Fase A4 — checklist de materiales copiado a cada orden al crearla.
 *
 * `cantidad`/`unidad` son opcionales (los materiales de Montaje FO llevan cantidad; los de
 * Habilitar caja no). En esta fase solo se crean los renglones: marcarlos es la Fase D (#1202).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('talento_work_order_checklist_items')) {
            return;
        }

        Schema::create('talento_work_order_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tarea_id')->index('twoci_tarea_idx');
            $table->string('label', 255);
            $table->decimal('cantidad', 10, 2)->nullable();
            $table->string('unidad', 30)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('checked')->default(false);
            $table->timestamp('checked_at')->nullable();
            $table->unsignedBigInteger('checked_by')->nullable();
            $table->timestamps();

            $table->foreign('tarea_id', 'twoci_tarea_fk')
                ->references('id')->on('tasks')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_work_order_checklist_items');
    }
};
