<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MR-11 — Entidad hilo (strand) de primera clase (aditiva, item roadmap #947).
 *
 * El hilo deja de ser un número en un campo (como en el mirror `mapared_fibers`, que
 * replica 1:1 el legacy `map_fibers` atado a una ruta/layer) y pasa a ser fila propia
 * atada al cable físico (`mapared_cables`, MR-09/#945), con su propio estado operativo.
 * Sin FK real a `mapared_cables` (mismo criterio que el resto de `mapared_*`, ver
 * comentario de MR-04 en 2026_09_04_160000_create_mapared_mirror_tables.php): MR-09 y
 * MR-11 avanzan en paralelo, ninguno bloquea al otro a nivel de esquema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapared_hilos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cable_id');
            $table->smallInteger('buffer');
            $table->smallInteger('numero');
            $table->string('color')->nullable();
            $table->enum('estado', ['libre', 'asignado', 'dañado', 'reservado'])->default('libre');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['cable_id', 'buffer', 'numero'], 'mapared_hilos_cable_buffer_numero_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapared_hilos');
    }
};
