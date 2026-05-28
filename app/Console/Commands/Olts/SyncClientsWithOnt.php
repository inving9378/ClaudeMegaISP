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
            "update client_additional_information cai INNER join olt_onus g ON cai.client_id=g.client_id SET cai.gpon_ont=g.unique_external_id;"
        );

        $this->info('Sincronización completada correctamente');
    }
}
