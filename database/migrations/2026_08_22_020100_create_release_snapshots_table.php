<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #1020 (sub-item 4/5 de #1012) — un snapshot por tabla afectada al momento en
 * que una versión quedó aplicada (dev o prod), para medir después cuántas filas NUEVAS llegaron
 * (decisión de Irving, pregunta q3: MAX(id) en vez de COUNT(*) — barato incluso en tablas
 * grandes, usa el índice de la PK en vez de escanear toda la tabla).
 *
 * `max_id_al_snapshot` queda NULL cuando la tabla no tiene PK autoincremental legible (caso
 * raro en este proyecto) — esas tablas se excluyen del cálculo de filas nuevas, no se inventa
 * un número.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained('releases')->cascadeOnDelete();
            $table->string('tabla', 128);
            $table->string('criticidad', 20);
            $table->unsignedBigInteger('max_id_al_snapshot')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['release_id', 'tabla']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_snapshots');
    }
};
