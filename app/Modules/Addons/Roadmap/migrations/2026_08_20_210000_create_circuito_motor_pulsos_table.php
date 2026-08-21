<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #875 — el latido de los motores del circuito. Una fila por corrida (ok o fallo), para que
 * el semáforo de la Torre muestre "última ejecución exitosa" como un HECHO, no un flag `enabled`.
 * Aditiva; no toca ninguna tabla existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('circuito_motor_pulsos')) {
            return;
        }
        Schema::create('circuito_motor_pulsos', function (Blueprint $table) {
            $table->id();
            $table->string('motor', 40)->index();
            $table->dateTime('inicio_at');
            $table->dateTime('fin_at')->nullable();
            $table->boolean('ok');
            $table->text('mensaje')->nullable();
            $table->unsignedInteger('duracion_ms')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circuito_motor_pulsos');
    }
};
