<?php

namespace App\Console\Commands\Scripts;

use App\Http\Repository\ClientMainInformationRepository;
use App\Services\InformationService;
use App\Services\LogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use function PHPUnit\Framework\isNull;

class TodosClientesConFechaUltimoPagoMenorEsteAnnoPasanAInactivo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:todos-clientes-con-fecha-ultimo-pago-menor-este-anno-pasan-a-inactivo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $informationService = new InformationService();
        $clients =$informationService->obtenerClientesConFechaDeUltimoPagoMenorAEsteAnno();

        foreach ($clients as $client){
            $logService = new LogService();
            $clientMainInformationRepository = new ClientMainInformationRepository();
            $clientMainInformationRepository->setClientMainInformationByClientId($client->id);
            $clientMainInformationRepository->setStateInactive();

            if(isNull($client->fecha_fin_periodo_gracia)){
                $client->fecha_fin_periodo_gracia = '2023-12-31';
                $client->save();
            }

            $logService->log($client,'Cliente # '.$client->id . ' actualizado a Inactivo por tener fecha de pago menor a 2024 , ejecutado desde script TodosClientesConFechaUltimoPagoMenorEsteAnnoPasanAInactivo');
        }
    }
}
