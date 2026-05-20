<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ia_tareas', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->enum('estado', ['pendiente', 'en_progreso', 'completada', 'cancelada'])->default('pendiente');
            $table->enum('prioridad', ['alta', 'media', 'baja'])->default('media');
            $table->string('modulo_relacionado')->nullable();
            $table->timestamp('completada_en')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('estado');
            $table->index('prioridad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_tareas');
    }
};
