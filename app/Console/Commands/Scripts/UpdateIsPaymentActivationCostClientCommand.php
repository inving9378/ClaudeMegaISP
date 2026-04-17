<?php

namespace App\Console\Commands\Scripts;

use App\Models\Client;
use App\Models\ClientMainInformation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateIsPaymentActivationCostClientCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-is-payment-activation-cost-client';

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

        /* A partir del cliente 3137 hacia abajo todos con instalacion gratis
si amount_technician_and_why es 0 fue gratis

comparar el amount_technician_and_why con el monto del primer pago si es igual No aplica  */
        // Obtener todos los clientes y recorrerlos
        Client::withoutEvents(function ()  {
            Client::with('client_main_information', 'client_additional_information')->chunk(100, function ($clients) {
                foreach ($clients as $client) {
                    $isPaymentActivationCost = false;
                    $firstPayment = $client->payments()->orderBy('date', 'asc')->first();

                    if ($firstPayment) {
                        $paymentAmount = $firstPayment->amount;
                    } else {
                        $paymentAmount = 0;
                    }

                    try {
                        if ($client->id <= 3137){
                            $isPaymentActivationCost = true;
                        } elseif ($paymentAmount == $client->client_additional_information->amount_technician_and_why && $paymentAmount != 0) {
                            $isPaymentActivationCost = false;
                        } else {
                            $isPaymentActivationCost = true;
                        }
                        // Deshabilitar Observers de client_main_information durante la actualización
                        ClientMainInformation::withoutEvents(function () use ($client, $isPaymentActivationCost) {
                            $client->client_main_information->update([
                                'is_payment_activation_cost' => $isPaymentActivationCost,
                            ]);
                        });
                    } catch (\Exception $e) {
                        // Loggear cualquier error que ocurra durante la actualización
                        Log::error('Error updating client activation payment:', [
                            'client_id' => $client->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
        });
    }
}
