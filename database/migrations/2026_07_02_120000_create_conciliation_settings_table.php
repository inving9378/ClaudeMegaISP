<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interruptores editables de la automatización de conciliación (WhatsApp IA).
 *
 * Tabla key-value aditiva. Expone los flags que hoy sólo viven en .env
 * (payments.wa_autorespond / auto_apply_enabled / id_cliente_auto_apply) como
 * estado editable desde el sistema, para poder encender/apagar sin tocar el
 * servidor durante la semana de observación.
 *
 * Semántica de lectura (ConciliationSettings::enabled): si existe fila para la
 * clave, manda su valor; si NO existe, se usa config('payments.<key>') (env)
 * como fallback. Arranca vacía → comportamiento idéntico al actual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conciliation_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->boolean('enabled')->default(false);
            $table->unsignedBigInteger('updated_by')->nullable(); // usuario que lo cambió (auditoría ligera)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conciliation_settings');
    }
};
