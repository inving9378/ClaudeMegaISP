<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cierre de orden de trabajo (Task) desde la app del técnico.
 * Persiste la geolocalización del cierre + referencias a la firma/foto.
 * La foto y la firma se guardan además como registros polimórficos en
 * `files` para que aparezcan en el visor de archivos de la tarea; aquí
 * se guarda el path de la firma y la primera foto para acceso directo.
 * Una fila por tarea (se hace upsert por task_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_closures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('gps_accuracy', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('signature_path')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique('task_id');
            $table->index('closed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_closures');
    }
};
