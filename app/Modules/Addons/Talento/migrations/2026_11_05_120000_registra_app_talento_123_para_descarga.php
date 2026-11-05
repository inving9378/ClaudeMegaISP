<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * App de campo 1.23 disponible para descarga (5-nov-2026, pedido de Irving).
 *
 * Producción anunciaba la 1.7 con un enlace a la red interna de dev, que no abre fuera de la
 * oficina. El APK se sirve desde dev.meganett.com.mx/downloads (sin contraseña de nginx, ver
 * deploy/nginx/dev-acceso/server-excepciones.conf); aquí solo se registra la versión, que es lo
 * que leen la tarjeta de descarga de la ficha (Dispositivos) y el aviso de actualización de la app.
 * Si ya hay una versión igual o más nueva registrada (dev), no hace nada.
 */
return new class extends Migration
{
    private const CODIGO = 123;

    public function up(): void
    {
        if (! Schema::hasTable('talento_app_releases')
            || DB::table('talento_app_releases')->where('version_code', '>=', self::CODIGO)->exists()) {
            return;
        }

        DB::transaction(function () {
            DB::table('talento_app_releases')->where('active', true)->update(['active' => false, 'updated_at' => now()]);
            DB::table('talento_app_releases')->insert([
                'version_name' => '1.23',
                'version_code' => self::CODIGO,
                'apk_url' => 'https://dev.meganett.com.mx/downloads/meganetV123.apk',
                'file_size' => 62506768,
                'changelog' => implode("\n", [
                    '- Mi día con las órdenes de hoy y las atrasadas',
                    '- Evidencias, firma del cliente y cierre de la orden desde el teléfono',
                    '- Entrada y salida con GPS, o escribiendo la dirección si falla',
                    '- Compensación y Liquidaciones iguales que en la web',
                    '- Credencial y cursos de Academia',
                ]),
                'is_mandatory' => false,
                'active' => true,
                'released_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /** No se revierte: la versión anterior registrada apunta a un enlace que no abre fuera de la oficina. */
    public function down(): void
    {
    }
};
