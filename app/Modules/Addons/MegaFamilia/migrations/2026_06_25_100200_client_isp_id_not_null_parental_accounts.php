<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * M3 — parental_accounts.client_isp_id → NOT NULL.
 *
 * Requiere 0 filas NULL (account#2 huérfana ya borrada). Además cambia la regla del
 * FK de ON DELETE SET NULL → CASCADE: coherente con las tablas hijas (M1) y necesario
 * porque SET NULL es incompatible con una columna NOT NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Idempotente: cada ALTER hace auto-commit en MySQL, así que un run parcial
        // previo pudo dejar el FK ya borrado (errno 1091) o ya recreado (errno 1826).
        // Resolvemos el nombre real del FK desde information_schema en vez de asumir
        // el nombre convencional de Laravel.

        // 1. Drop del FK actual (cualquier nombre) solo si existe.
        if ($fk = $this->foreignKeyName()) {
            DB::statement("ALTER TABLE parental_accounts DROP FOREIGN KEY `{$fk}`");
        }

        // 2. No se puede poner NOT NULL si hay filas huérfanas con client_isp_id NULL.
        //    (El borrado de account#2 pudo no haberse aplicado en este entorno.)
        $nulls = DB::table('parental_accounts')->whereNull('client_isp_id')->count();
        if ($nulls > 0) {
            throw new \RuntimeException(
                "Abortado: {$nulls} fila(s) en parental_accounts con client_isp_id NULL. "
                . 'Backfilléalas o bórralas antes de re-correr esta migración.'
            );
        }

        // 3. NOT NULL (idempotente: re-aplicarlo no daña).
        DB::statement('ALTER TABLE parental_accounts MODIFY client_isp_id BIGINT UNSIGNED NOT NULL');

        // 4. Recrear el FK con CASCADE solo si no quedó alguno tras el paso 1.
        if (! $this->foreignKeyName()) {
            DB::statement('ALTER TABLE parental_accounts ADD CONSTRAINT parental_accounts_client_isp_id_foreign FOREIGN KEY (client_isp_id) REFERENCES clients(id) ON DELETE CASCADE');
        }
    }

    /** Nombre real del FK sobre parental_accounts.client_isp_id, o null si no existe. */
    private function foreignKeyName(): ?string
    {
        $row = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1',
            ['parental_accounts', 'client_isp_id']
        );

        return $row->CONSTRAINT_NAME ?? null;
    }

    public function down(): void
    {
        if ($fk = $this->foreignKeyName()) {
            DB::statement("ALTER TABLE parental_accounts DROP FOREIGN KEY `{$fk}`");
        }
        DB::statement('ALTER TABLE parental_accounts MODIFY client_isp_id BIGINT UNSIGNED NULL');
        if (! $this->foreignKeyName()) {
            DB::statement('ALTER TABLE parental_accounts ADD CONSTRAINT parental_accounts_client_isp_id_foreign FOREIGN KEY (client_isp_id) REFERENCES clients(id) ON DELETE SET NULL');
        }
    }
};
