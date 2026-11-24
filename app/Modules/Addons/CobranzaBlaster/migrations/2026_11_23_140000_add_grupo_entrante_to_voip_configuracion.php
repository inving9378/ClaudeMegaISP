<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-11-23 (pedido de Irving) — Campañas/Blaster usa SU troncal (Config. VoIP) para
 * salir y para entrar. Las llamadas que entran por ella (quien regresa la llamada al
 * número de las campañas) timbran en un grupo de timbrado de VoIP; vacío = el primer
 * grupo activo. Sin FK dura: voip_grupos_timbrado vive en el addon de VoIP.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('voip_configuracion') && ! Schema::hasColumn('voip_configuracion', 'grupo_entrante_id')) {
            Schema::table('voip_configuracion', function (Blueprint $table) {
                $table->unsignedBigInteger('grupo_entrante_id')->nullable()->after('callerid_numero');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('voip_configuracion', 'grupo_entrante_id')) {
            Schema::table('voip_configuracion', function (Blueprint $table) {
                $table->dropColumn('grupo_entrante_id');
            });
        }
    }
};
