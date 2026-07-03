<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de descarte para imágenes que NO son comprobantes.
 *
 * Los clientes mandan por WhatsApp muchas imágenes que no son comprobantes
 * (capturas de chat, memes, fotos del módem…). Tras extraer, si la imagen no
 * tiene datos de pago (ni monto ni clave de rastreo), se marca discarded_at y
 * NO se crea sesión/caso en la cola. La marca evita reprocesar (re-llamar a la
 * IA) el mismo mensaje. Aditivo: solo agrega columnas nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_payment_extractions', function (Blueprint $table) {
            $table->timestamp('discarded_at')->nullable()->index()->after('extracted_at');
            $table->string('discard_reason')->nullable()->after('discarded_at');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_payment_extractions', function (Blueprint $table) {
            $table->dropColumn(['discarded_at', 'discard_reason']);
        });
    }
};
