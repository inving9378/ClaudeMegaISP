<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega 'daily' al enum period de talento_compensation_rules (pedido de Irving:
 * el modal de "Nueva regla" solo ofrecía Semanal/Quincenal/Mensual). Aditiva —
 * ALTER MODIFY que agrega un valor nuevo, no toca los 3 existentes ni las filas
 * ya guardadas (verificado: 0 reglas en dev al momento de escribir esto).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE talento_compensation_rules MODIFY COLUMN period ENUM('daily','weekly','biweekly','monthly') NOT NULL DEFAULT 'weekly'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE talento_compensation_rules MODIFY COLUMN period ENUM('weekly','biweekly','monthly') NOT NULL DEFAULT 'weekly'");
    }
};
