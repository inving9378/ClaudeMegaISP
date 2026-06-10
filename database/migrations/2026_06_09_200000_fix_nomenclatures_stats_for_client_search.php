<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // nomenclatures.client_id ya tiene el FK index nomenclatures_client_id_foreign,
        // pero las estadísticas de InnoDB estaban a 0 filas → el optimizer hacía type=ALL.
        // ANALYZE TABLE actualiza las stats y permite que MySQL use el índice existente.
        DB::statement('ANALYZE TABLE nomenclatures');

        // Por si acaso, garantizamos que las demás tablas del filtering_query
        // también tienen índice en client_id (ya existen, esto es idempotente).
        foreach ([
            'client_main_information'       => 'client_main_information_client_id_index',
            'client_additional_information' => 'client_additional_information_client_id_index',
            'billing_configurations'        => 'billing_configurations_client_id_index',
            'network_ips'                   => 'network_ips_client_id_index',
            'client_internet_services'      => 'client_internet_services_client_id_index',
        ] as $table => $expectedIndex) {
            $indexes = collect(DB::select("SHOW INDEX FROM `{$table}`"))
                ->pluck('Key_name')->all();
            if (! in_array($expectedIndex, $indexes)) {
                Schema::table($table, function ($t) use ($table) {
                    $t->index('client_id');
                });
            }
        }
    }

    public function down(): void
    {
        // ANALYZE TABLE no es reversible ni necesaria deshacer.
    }
};
