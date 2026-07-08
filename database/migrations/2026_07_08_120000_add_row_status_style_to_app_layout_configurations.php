<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preferencia POR USUARIO del estilo de fila por estado en las tablas:
 *   'underline' (línea inferior de color, comportamiento actual/default)
 *   'filled'    (fondo relleno de color por estado)
 *
 * Mismo mecanismo que `color_mode` (modo claro/oscuro): se persiste en
 * app_layout_configurations y se aplica desde el body del master.
 * Aditiva e idempotente (guard hasColumn). NUNCA destructiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('app_layout_configurations', 'row_status_style')) {
            Schema::table('app_layout_configurations', function (Blueprint $table) {
                $table->string('row_status_style', 20)->default('underline')->after('client_datatable_color');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('app_layout_configurations', 'row_status_style')) {
            Schema::table('app_layout_configurations', function (Blueprint $table) {
                $table->dropColumn('row_status_style');
            });
        }
    }
};
