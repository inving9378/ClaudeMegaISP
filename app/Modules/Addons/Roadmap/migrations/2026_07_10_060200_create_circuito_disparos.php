<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría de disparos manuales del Circuito (#337): una fila por cada pulsación de
 * "Ejecutar vuelta ahora" o marca "Urgente". Registra quién, cuándo, el origen y (si aplica)
 * el item que lo motivó, más cuándo lo consumió el picker. Aditiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('circuito_disparos')) {
            return;
        }
        Schema::create('circuito_disparos', function (Blueprint $table) {
            $table->id();
            $table->string('requested_by')->nullable();   // login_user/email de quien disparó
            $table->string('origin', 20)->default('boton'); // 'boton' | 'urgente'
            $table->unsignedBigInteger('item_id')->nullable(); // item que motivó el disparo (urgente)
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('consumed_at')->nullable();  // cuándo lo tomó el picker
            $table->timestamps();
            $table->index('requested_at');
            $table->index('consumed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circuito_disparos');
    }
};
