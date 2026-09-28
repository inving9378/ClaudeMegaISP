<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bug preexistente encontrado al probar el Agente de Ventas (IA): `tickets.group`
 * es ENUM('Cualquier','IT','Finanzas','Ventas') — NO incluye 'Instalaciones',
 * que es justo el valor que `WhatsAppCrmService::scheduleInstallation()` ya
 * manda hardcodeado desde antes de esta sesión. Como resultado, ese método
 * nunca pudo crear un ticket con éxito (confirmado: 0 filas históricas con
 * group='Instalaciones' en dev, con STRICT_TRANS_TABLES la inserción truena).
 *
 * Fix aditivo: amplía el ENUM para aceptar 'Instalaciones' sin tocar los
 * valores existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE tickets MODIFY COLUMN `group`
            ENUM('Cualquier','IT','Finanzas','Ventas','Instalaciones') NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE tickets SET `group`='Ventas' WHERE `group`='Instalaciones'");
        DB::statement("ALTER TABLE tickets MODIFY COLUMN `group`
            ENUM('Cualquier','IT','Finanzas','Ventas') NULL");
    }
};
