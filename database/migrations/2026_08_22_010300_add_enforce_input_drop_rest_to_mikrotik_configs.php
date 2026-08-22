<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #983 — flag de rollout gradual por router para la regla de
 * firewall MgNet_INPUT_DROPEA_EL_RESTO (ver MikrotikRulesJob::handle()).
 * Default false: ningún router queda afectado hasta que se active a mano
 * (decisión de Irving, opción recomendada q2 del item).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('mikrotik_configs', 'enforce_input_drop_rest')) {
            Schema::table('mikrotik_configs', function (Blueprint $table) {
                $table->boolean('enforce_input_drop_rest')->default(false)->after('meganet_config_ip_address_enable');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mikrotik_configs', 'enforce_input_drop_rest')) {
            Schema::table('mikrotik_configs', function (Blueprint $table) {
                $table->dropColumn('enforce_input_drop_rest');
            });
        }
    }
};
