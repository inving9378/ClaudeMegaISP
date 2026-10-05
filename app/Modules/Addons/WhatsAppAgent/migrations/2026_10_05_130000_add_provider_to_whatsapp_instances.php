<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item: migrar WhatsApp de Evolution API (no oficial) a la API oficial de
 * Meta (WhatsApp Business Platform / Cloud API), configurable por línea.
 *
 * `provider` vive POR INSTANCIA (línea de WhatsApp), no como un flag global:
 * así se puede migrar una línea a la vez sin apagar las demás. Default
 * 'evolution' para que las filas ya existentes sigan funcionando exactamente
 * igual (aditivo, sin romper nada).
 *
 * Las credenciales de la vía oficial (access token, phone_number_id, etc.)
 * NO viven en esta tabla -- viven en el Integration Hub (api_integrations,
 * provider='whatsapp-oficial'), igual que ya funciona para los proveedores de
 * IA: cifradas, con el mismo mecanismo ya probado, en vez de inventar
 * columnas nuevas en whatsapp_instances para un secreto real de Meta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_instances', 'provider')) {
                $table->string('provider', 20)->default('evolution')->after('slug')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_instances', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_instances', 'provider')) {
                $table->dropColumn('provider');
            }
        });
    }
};
