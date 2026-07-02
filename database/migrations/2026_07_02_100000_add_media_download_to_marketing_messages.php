<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 1 (conciliación de pagos por IA) — Ladrillo 1.
 *
 * Descarga del binario de la imagen de comprobante que llega por WhatsApp.
 * Hoy ProcessIncomingMessageJob guarda el payload de Evolution en
 * marketing_messages.metadata pero NO baja el archivo (la url es cifrada).
 *
 * Columnas ADITIVAS (nullable) para persistir el binario ya descargado:
 *   - media_path:          ruta relativa en el disco 'local' (privado).
 *   - media_downloaded_at: sello de descarga → sirve de guardia de idempotencia.
 *
 * NO se toca la columna existente `media_paths` (json, prevista para envíos
 * salientes, hoy sin uso). Migración aditiva, sin data-loss.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('marketing_messages', 'media_path')) {
                $table->string('media_path')->nullable()->after('media_paths');
            }
            if (!Schema::hasColumn('marketing_messages', 'media_downloaded_at')) {
                $table->timestamp('media_downloaded_at')->nullable()->after('media_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketing_messages', function (Blueprint $table) {
            if (Schema::hasColumn('marketing_messages', 'media_downloaded_at')) {
                $table->dropColumn('media_downloaded_at');
            }
            if (Schema::hasColumn('marketing_messages', 'media_path')) {
                $table->dropColumn('media_path');
            }
        });
    }
};
