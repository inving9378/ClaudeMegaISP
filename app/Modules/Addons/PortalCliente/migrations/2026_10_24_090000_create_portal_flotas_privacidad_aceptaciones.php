<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso de privacidad del rastreo (Mis Vehículos, #636): quién lo aceptó, cuándo y qué versión del texto.
 * Aditiva: solo crea la tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('portal_flotas_privacidad_aceptaciones')) {
            return;
        }
        Schema::create('portal_flotas_privacidad_aceptaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->index();
            $table->unsignedBigInteger('usuario_id')->nullable();   // id de la sesión del portal (client_main_information)
            $table->string('version', 20);
            $table->timestamp('aceptado_at');
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['client_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_flotas_privacidad_aceptaciones');
    }
};
