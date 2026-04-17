<?php

namespace App\Console\Commands\Olts;

use App\Models\Olt;
use App\Services\OLTsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncClientsWithOnt extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'smartolt:sync-clients-with-ont';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Para sincronizar los clientes con ont en la app Samrt OLT';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Iniciando sincronización");
        DB::statement(
            "WITH extracted_data AS (
                SELECT
                    unique_external_id,
                    signal_1490,
                    CASE
                            WHEN name REGEXP '[0-9]{3,}' 
                            THEN CAST(REGEXP_SUBSTR(name, '[0-9]{3,}') AS UNSIGNED)
                            WHEN name REGEXP '[0-9]+' 
                            THEN CAST(REGEXP_SUBSTR(name, '[0-9]+') AS UNSIGNED)        
                            ELSE NULL 
                        END AS id_str
                FROM olt_onus WHERE NAME NOT IN ('Local2', 'BODEGA MEGANET 1')
            ),
            gouped_data AS (
                SELECT
                    unique_external_id,
                    signal_1490, 
                    MAX(CAST(id_str AS UNSIGNED)) AS client_id
                FROM extracted_data
                WHERE id_str IS NOT NULL
                GROUP BY unique_external_id, signal_1490
            )
            update client_additional_information cai INNER join gouped_data g ON cai.client_id=g.client_id SET cai.gpon_ont=g.unique_external_id, cai.olt_power_dbm=g.signal_1490;"
        );

        $this->info('Sincronización completada correctamente');
    }
}
