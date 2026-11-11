<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * App de campo 1.27 disponible para descarga (11-nov-2026, pedido de David).
 *
 * Viaja en la misma versión que su servidor (fotos de la evidencia y «El cliente canceló», #1326/#1327):
 * con un servidor anterior la app reportaría un motivo que no existe. El APK se sirve desde
 * dev.meganett.com.mx/downloads, como la 1.23; aquí solo se registra la versión, que es lo que leen la
 * tarjeta de descarga de la ficha (Dispositivos) y el aviso de actualización de la app.
 * Si ya hay una versión igual o más nueva registrada, no hace nada.
 */
return new class extends Migration
{
    private const CODIGO = 127;

    public function up(): void
    {
        if (! Schema::hasTable('talento_app_releases')
            || DB::table('talento_app_releases')->where('version_code', '>=', self::CODIGO)->exists()) {
            return;
        }

        DB::transaction(function () {
            DB::table('talento_app_releases')->where('active', true)->update(['active' => false, 'updated_at' => now()]);
            DB::table('talento_app_releases')->insert([
                'version_name' => '1.27',
                'version_code' => self::CODIGO,
                'apk_url' => 'https://dev.meganett.com.mx/downloads/meganetV127.apk',
                'file_size' => 56534934,
                'changelog' => implode("\n", [
                    '- Nuevo color azul',
                    '- Fotos de la evidencia en el detalle de la orden; tócalas para verlas en grande',
                    '- Globo en Mi día con las órdenes pendientes',
                    '- «El cliente canceló» al reportar que no se pudo completar: la orden pasa a tu supervisor',
                    '- Calendario de órdenes',
                    '- Órdenes atrasadas en Mi día y botón para reprogramar',
                    '- Arreglo: la foto de la evidencia ya se guarda',
                ]),
                'is_mandatory' => false,
                'active' => true,
                'released_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /** Vuelve a dejar activa la versión anterior. */
    public function down(): void
    {
        DB::transaction(function () {
            DB::table('talento_app_releases')->where('version_code', self::CODIGO)->delete();
            $anterior = DB::table('talento_app_releases')->orderByDesc('version_code')->value('id');
            $anterior && DB::table('talento_app_releases')->where('id', $anterior)->update(['active' => true, 'updated_at' => now()]);
        });
    }
};
