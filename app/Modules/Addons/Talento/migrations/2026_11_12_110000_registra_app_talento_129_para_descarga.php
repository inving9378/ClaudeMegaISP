<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * App de campo 1.29 disponible para descarga (12-nov-2026, pedido de David, #1286).
 *
 * Viaja en la misma versión que su servidor: las fotos de corrección necesitan
 * POST /talento/api/ots/{id}/correccion/enviar y la corrección en el detalle de la orden. El APK se
 * sirve desde dev.meganett.com.mx/downloads, como la 1.28; aquí solo se registra la versión, que es lo
 * que leen la tarjeta de descarga de la ficha (Dispositivos) y el aviso de actualización de la app.
 * Si ya hay una versión igual o más nueva registrada, no hace nada.
 */
return new class extends Migration
{
    private const CODIGO = 129;

    public function up(): void
    {
        if (! Schema::hasTable('talento_app_releases')
            || DB::table('talento_app_releases')->where('version_code', '>=', self::CODIGO)->exists()) {
            return;
        }

        DB::transaction(function () {
            DB::table('talento_app_releases')->where('active', true)->update(['active' => false, 'updated_at' => now()]);
            DB::table('talento_app_releases')->insert([
                'version_name' => '1.29',
                'version_code' => self::CODIGO,
                'apk_url' => 'https://dev.meganett.com.mx/downloads/meganetV129.apk',
                'file_size' => 56544042,
                'changelog' => implode("\n", [
                    '- Si tu supervisor pide una corrección en una orden terminada, la ves en Mi día con el motivo; subes las fotos y se la envías',
                    '- Lectura dBm, Speedtest y Foto después aceptan varias fotos',
                    '- En Evidencias ves las fotos que ya tomaste; si una salió mal, la quitas y tomas otra',
                    '- Nuevo color azul',
                    '- Fotos de la evidencia en el detalle de la orden; tócalas para verlas en grande',
                    '- Globo en Mi día con las órdenes pendientes',
                    '- «El cliente canceló» al reportar que no se pudo completar: la orden pasa a tu supervisor',
                    '- Calendario de órdenes',
                    '- Órdenes atrasadas en Mi día y botón para reprogramar',
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
