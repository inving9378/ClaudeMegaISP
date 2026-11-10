<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #1311 (Recorridos, fase a) — qué orden de trabajo (tasks) nació de qué hallazgo. Un hallazgo
 * puede dar varias órdenes y una orden puede atender varios hallazgos. No agrega estados ni
 * columnas a `tasks`. La llena la evaluación del supervisor (#1312).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('talento_hallazgo_ordenes')) {
            return;
        }

        Schema::create('talento_hallazgo_ordenes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hallazgo_id');
            $table->unsignedBigInteger('tarea_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['hallazgo_id', 'tarea_id'], 'thord_hal_tarea_unique');
            $table->index('tarea_id', 'thord_tarea_idx');
            $table->foreign('hallazgo_id', 'thord_hal_fk')
                ->references('id')->on('talento_hallazgos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_hallazgo_ordenes');
    }
};
