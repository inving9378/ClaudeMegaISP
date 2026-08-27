<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El icono de JARVIS es un ajuste GLOBAL: se ve igual para todos (decisión de Irving, 2026-08-27).
 *
 * Por eso vive en el singleton de la Torre y no en una preferencia por usuario: JARVIS es UNA
 * identidad, y que cada quien viera una distinta convertiría el icono en decoración en vez de en
 * lo que es — la cara de un sistema que todos miran y sobre el que todos comentan.
 *
 * Guarda el SLUG del icono, no una ruta ni un binario: las rutas se derivan del catálogo
 * (`JarvisIconosService`), así que renombrar carpetas o cambiar de tamaños no invalida lo elegido.
 * `null` = el de fábrica; nunca queda un valor apuntando a un archivo que ya no está.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('torre_config', function (Blueprint $table) {
            if (! Schema::hasColumn('torre_config', 'jarvis_icono')) {
                $table->string('jarvis_icono', 60)->nullable()->after('valvula_guarda_razon');
            }
        });
    }

    public function down(): void
    {
        Schema::table('torre_config', function (Blueprint $table) {
            if (Schema::hasColumn('torre_config', 'jarvis_icono')) {
                $table->dropColumn('jarvis_icono');
            }
        });
    }
};
