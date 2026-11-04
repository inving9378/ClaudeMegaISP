<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monitoreo de dedicados (item #988, Fase 3), en el MISMO registro de routers/NAS:
 *
 * - `login_api_ro` / `password_api_ro`: usuario de SOLO LECTURA para leer contadores y hacer
 *   ping. Mientras Irving no lo cree, el monitoreo usa el usuario actual (login_api) como
 *   respaldo y lo dice en el dry-run. La contraseña se guarda cifrada con la llave de la app.
 * - `port_api_ssl`: puerto de API-SSL, para el paso posterior (#998). Hoy no se usa.
 * - `identidad_esperada`: el /system identity que debe contestar ese router. El colector no lee
 *   un router sin identidad esperada, ni uno que conteste otra (red de seguridad contra leer el
 *   equipo equivocado, p. ej. por la redirección de RouterConnection en entornos no production).
 *
 * La identidad de cada router se captura aparte en cada instalación, nunca en el código: sin ella
 * el colector no lee ese router.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mikrotiks', function (Blueprint $table) {
            if (! Schema::hasColumn('mikrotiks', 'login_api_ro')) {
                $table->string('login_api_ro', 100)->nullable()->after('port_api')
                    ->comment('Usuario de solo lectura para el monitoreo de dedicados');
            }
            if (! Schema::hasColumn('mikrotiks', 'password_api_ro')) {
                $table->text('password_api_ro')->nullable()->after('login_api_ro')
                    ->comment('Cifrada (Crypt::encryptString)');
            }
            if (! Schema::hasColumn('mikrotiks', 'port_api_ssl')) {
                $table->unsignedInteger('port_api_ssl')->nullable()->after('password_api_ro');
            }
            if (! Schema::hasColumn('mikrotiks', 'identidad_esperada')) {
                $table->string('identidad_esperada', 191)->nullable()->after('port_api_ssl')
                    ->comment('/system identity que debe contestar este router');
            }
        });
    }

    public function down(): void
    {
        $columnas = array_values(array_filter(
            ['login_api_ro', 'password_api_ro', 'port_api_ssl', 'identidad_esperada'],
            fn ($c) => Schema::hasColumn('mikrotiks', $c)
        ));
        if ($columnas !== []) {
            Schema::table('mikrotiks', fn (Blueprint $table) => $table->dropColumn($columnas));
        }
    }
};
