<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ticket_plazos')) {
            return;
        }
        Schema::create('ticket_plazos', function (Blueprint $table) {
            $table->id();
            // null = aplica a cualquier tipo / cualquier prioridad (comodín).
            $table->string('type')->nullable();
            $table->unsignedTinyInteger('priority')->nullable();
            $table->unsignedInteger('horas_respuesta')->nullable();
            $table->unsignedInteger('horas_resolucion')->nullable();
            $table->timestamps();
            $table->unique(['type', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_plazos');
    }
};
