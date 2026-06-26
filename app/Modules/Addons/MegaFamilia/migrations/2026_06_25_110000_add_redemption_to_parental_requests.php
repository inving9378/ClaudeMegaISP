<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Habilita el flujo de CANJE de recompensas en el portal MegaFamilia:
 *  - `type` gana el valor 'redemption' (los 3 previos = solicitudes de permiso).
 *  - `reward_id` (FK nullable → parental_rewards) referencia la recompensa-catálogo
 *    que el hijo quiere canjear. Solo se setea en solicitudes type='redemption'.
 *
 * Aditiva y NO destructiva: no toca filas existentes (todas conservan su type).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE parental_requests MODIFY type ENUM('time_extra','app_unlock','web_unlock','redemption') NOT NULL");

        if (! Schema::hasColumn('parental_requests', 'reward_id')) {
            Schema::table('parental_requests', function (Blueprint $t) {
                $t->foreignId('reward_id')->nullable()->after('device_id')
                    ->constrained('parental_rewards')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('parental_requests', 'reward_id')) {
            Schema::table('parental_requests', function (Blueprint $t) {
                $t->dropForeign(['reward_id']);
                $t->dropColumn('reward_id');
            });
        }

        DB::statement("ALTER TABLE parental_requests MODIFY type ENUM('time_extra','app_unlock','web_unlock') NOT NULL");
    }
};
