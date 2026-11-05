<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UNI-T C2 (#878) — historial de cambios de seller_id / colaborador_id en client_main_information.
 * Aditiva: tabla nueva, no toca datos existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('identidad_cambios_historial')) {
            return;
        }
        Schema::create('identidad_cambios_historial', function (Blueprint $table) {
            $table->id();
            $table->string('tabla', 64);
            $table->unsignedBigInteger('registro_id');
            $table->string('campo', 32);
            $table->unsignedBigInteger('valor_anterior')->nullable();
            $table->unsignedBigInteger('valor_nuevo')->nullable();
            $table->string('origen', 32)->default('app');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tabla', 'registro_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identidad_cambios_historial');
    }
};
