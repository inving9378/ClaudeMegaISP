<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convierte ps_endpoints.identify_by de ENUM a VARCHAR(80).
 *
 * Asterisk 20.19.0 usa identify_by como lista separada por comas
 * (ej. 'ip,username') — imposible con ENUM. El alembic oficial lo convirtió
 * en la migración 52798ad97bdf_add_pjsip_identify_by_header.py.
 *
 * Estado actual en esta instalación: ENUM('username') — solo acepta
 * 'username' como valor único, rechazando cualquier combinación como 'ip,username'.
 */
return new class extends Migration
{
    protected $connection = 'asterisk_rt';

    public function up(): void
    {
        $rows = DB::connection('asterisk_rt')->select("SHOW COLUMNS FROM `ps_endpoints` LIKE 'identify_by'");
        if (empty($rows)) {
            return;
        }
        $type = strtolower($rows[0]->Type ?? '');
        if (str_contains($type, 'enum') || str_contains($type, 'varchar(40)')) {
            DB::connection('asterisk_rt')->statement(
                "ALTER TABLE `ps_endpoints` MODIFY COLUMN `identify_by` VARCHAR(80) NULL DEFAULT NULL"
            );
        }
    }

    public function down(): void
    {
        DB::connection('asterisk_rt')->statement(
            "ALTER TABLE `ps_endpoints` MODIFY COLUMN `identify_by` ENUM('username','auth_username','ip') NULL DEFAULT NULL"
        );
    }
};
