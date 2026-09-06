<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MR-12 — Entidad empalme: unión de hilos (aditiva, item roadmap #948).
 *
 * `mapared_empalmes` es el modelo NUEVO y limpio de empalme (thread union), independiente de
 * las tablas espejo de MR-04. Une "hilo A" (columna `hilo_a_id`, apunta a `mapared_hilos.id` de
 * MR-11) con un segundo extremo polimórfico (`extremo_b_type`/`extremo_b_id`), que puede ser
 * OTRO hilo (empalme hilo-hilo, ej. Rack→NAP, NAP→NAP) o un puerto de `mapared_puertos` de
 * MR-10 (empalme hilo→puerto de splitter).
 *
 * Igual que `mapared_puertos` (MR-10), sin FK real hacia `mapared_hilos`: MR-11 se está
 * construyendo en paralelo y esta tabla no debe depender de su orden de merge (convención ya
 * establecida por MR-04/MR-10 de no cruzar FKs reales entre tablas `mapared_*` en evolución).
 *
 * El "elemento contenedor" (mufa/NAP/rack) también es polimórfico: mufa y NAP son ambos
 * `MapaRedLayer` (distinguidos por su columna `dialog` = junction_box/service_box), rack es
 * `MapaRedDevice` (columna `type` = rack).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapared_empalmes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hilo_a_id');
            $table->string('extremo_b_type');
            $table->unsignedBigInteger('extremo_b_id');
            $table->string('elemento_contenedor_type');
            $table->unsignedBigInteger('elemento_contenedor_id');
            $table->string('bandeja')->nullable();
            $table->string('posicion')->nullable();
            $table->enum('tipo', ['fusion', 'mecanico', 'conectorizado']);
            $table->decimal('perdida_db', 5, 2)->nullable();
            $table->date('fecha');
            $table->unsignedBigInteger('tecnico_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('hilo_a_id');
            $table->index(['extremo_b_type', 'extremo_b_id'], 'mapared_empalmes_extremo_b_index');
            $table->index(['elemento_contenedor_type', 'elemento_contenedor_id'], 'mapared_empalmes_contenedor_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapared_empalmes');
    }
};
