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
        DB::statement('ALTER TABLE parental_accounts DROP FOREIGN KEY parental_accounts_client_isp_id_foreign');
        DB::statement('ALTER TABLE parental_accounts MODIFY client_isp_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE parental_accounts ADD CONSTRAINT parental_accounts_client_isp_id_foreign FOREIGN KEY (client_isp_id) REFERENCES clients(id) ON DELETE CASCADE');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE parental_accounts DROP FOREIGN KEY parental_accounts_client_isp_id_foreign');
        DB::statement('ALTER TABLE parental_accounts MODIFY client_isp_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE parental_accounts ADD CONSTRAINT parental_accounts_client_isp_id_foreign FOREIGN KEY (client_isp_id) REFERENCES clients(id) ON DELETE SET NULL');
    }
};
