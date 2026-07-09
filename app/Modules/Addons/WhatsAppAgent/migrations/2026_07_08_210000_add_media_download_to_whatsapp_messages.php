<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aditiva idempotente: columnas para el binario de media descargado por el
 * gateway (WhatsAppGateway::downloadMedia). `media_path` = ruta en disco
 * privado; `media_downloaded_at` = timestamp de descarga (idempotencia).
 * whatsapp_messages ya tenía media_url/filename/mime/size; estas dos faltaban
 * para el contrato de conciliación (que lee media_path + Storage local).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_messages', 'media_path')) {
                $table->string('media_path')->nullable()->after('media_size');
            }
            if (!Schema::hasColumn('whatsapp_messages', 'media_downloaded_at')) {
                $table->timestamp('media_downloaded_at')->nullable()->after('media_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            foreach (['media_path', 'media_downloaded_at'] as $col) {
                if (Schema::hasColumn('whatsapp_messages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
