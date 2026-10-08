<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asegura que exista la tabla COMPARTIDA del núcleo `notifications` (la campana
 * de todo MEGAISP). En MEGANET ya existe (legacy, viene de los dumps SQL), pero
 * NINGUNA migración del repo la crea → en una VM nueva de otro ISP con `migrate`
 * limpio podría faltar, y sin ella el canal de campana del módulo (y de todo el
 * sistema) no funciona.
 *
 * ⚠️ CANDADO DE SEGURIDAD (decisión de Irving): este módulo SOLO la CREA si falta.
 *    JAMÁS la elimina. Por eso `down()` es intencionalmente un NO-OP: al
 *    desinstalar Monitoreo Oficial no se debe tocar una tabla compartida del
 *    núcleo que usan la campana y otros módulos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications')) {
            return; // ya existe (MEGANET y la mayoría de instancias): no se toca.
        }

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');          // notifiable_type + notifiable_id
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // NO-OP A PROPÓSITO. `notifications` es tabla compartida del núcleo.
        // Desinstalar este módulo NUNCA debe borrar la campana del sistema.
    }
};
