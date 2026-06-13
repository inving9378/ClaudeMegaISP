<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige el tipo de columnas booleanas en ps_contacts.
 *
 * Asterisk 20 escribe los campos booleanos PJSIP realtime como strings
 * 'true'/'false' (no como 0/1), por lo que TINYINT los rechaza:
 *   HY000: Incorrect integer value: 'false' for column 'qualify_2xx_only'
 *
 * Los campos afectados deben ser VARCHAR(5) para aceptar 'true'/'false'
 * y también 'yes'/'no' según la versión de Asterisk.
 *
 * Aplica solo a las columnas booleanas que agregamos en migraciones previas
 * (prune_on_boot, authenticate_qualify, qualify_2xx_only).
 */
return new class extends Migration
{
    protected $connection = 'asterisk_rt';

    private array $boolColumns = [
        'prune_on_boot',
        'authenticate_qualify',
        'qualify_2xx_only',
    ];

    private function asteriskAvailable(): bool
    {
        try {
            DB::connection('asterisk_rt')->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function up(): void
    {
        if (! $this->asteriskAvailable()) {
            return;
        }

        foreach ($this->boolColumns as $col) {
            if (Schema::connection('asterisk_rt')->hasColumn('ps_contacts', $col)) {
                DB::connection('asterisk_rt')->statement(
                    "ALTER TABLE ps_contacts MODIFY COLUMN `{$col}` VARCHAR(5) NULL DEFAULT NULL"
                );
            }
        }
    }

    public function down(): void
    {
        if (! $this->asteriskAvailable()) {
            return;
        }

        foreach ($this->boolColumns as $col) {
            if (Schema::connection('asterisk_rt')->hasColumn('ps_contacts', $col)) {
                DB::connection('asterisk_rt')->statement(
                    "ALTER TABLE ps_contacts MODIFY COLUMN `{$col}` TINYINT(1) NULL DEFAULT 0"
                );
            }
        }
    }
};
