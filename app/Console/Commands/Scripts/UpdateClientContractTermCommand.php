<?php

namespace App\Console\Commands\Scripts;

use App\Http\Repository\DurationContractRepository;
use App\Models\Client;
use App\Models\ClientMainInformation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateClientContractTermCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-client-contract-term';

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
        // Obtener todos los clientes y recorrerlos
        Client::withoutEvents(function ()  {
            Client::with('client_main_information')->chunk(100, function ($clients) {
                foreach ($clients as $client) {
                    $durationContractRepository = new DurationContractRepository();
                    try {
                        if ($client->created_at->lt('2023-07-01')) {
                            // Clientes creados antes de julio de 2023
                            $durationContractId = $durationContractRepository->getIdByDurationContract(6);
                        } elseif ($client->created_at->lt('2024-09-12')) {
                            // Clientes creados desde julio de 2023 hasta el 11 de septiembre de 2024
                            $durationContractId = $durationContractRepository->getIdByDurationContract(12);
                        } else {
                            // Clientes creados desde el 12 de septiembre de 2024 hasta la fecha actual
                            $durationContractId = $durationContractRepository->getIdByDurationContract(18);
                        }

                        // Deshabilitar Observers de client_main_information durante la actualización
                        ClientMainInformation::withoutEvents(function () use ($client, $durationContractId) {
                            $client->client_main_information->update([
                                'duration_contract_id' => $durationContractId,
                            ]);
                        });
                    } catch (\Exception $e) {
                        // Loggear cualquier error que ocurra durante la actualización
                        Log::error('Error updating client duration contract', [
                            'client_id' => $client->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
        });
    }
}
