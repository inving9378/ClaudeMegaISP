<?php

namespace App\Jobs\Client\BillingService;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Http\Repository\ClientRepository;
use App\Http\Controllers\Utils\TypeOfBillingController;
use App\Jobs\Client\ClientServiceChargedJob;

class RectifyBalanceAndCreateTransaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $clientService;
    protected $clientRepository;
    protected $planRelation;
    protected $transaction;

    protected $cuantasVecesSeLePuedeCobrar;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($clientService, $cuantasVecesSeLePuedeCobrar = 1, $transaction = null)
    {
        $this->clientService = $clientService;
        $this->planRelation = $clientService->getPlanRelation();
        $this->cuantasVecesSeLePuedeCobrar = $cuantasVecesSeLePuedeCobrar;
        $this->transaction = $transaction;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $util = new TypeOfBillingController($this->clientService);
        $newBalanceAndPrice = $util->getNewBalanceAndPriceByTypeOfBilling();

        if ($newBalanceAndPrice) {

            if ($this->cuantasVecesSeLePuedeCobrar > 1) {
                $newBalanceAndPrice = $this->modificoYObtengoElNewBalanceAndPriceSegunLaCantidadDeVecesQueSelePuedeCobrar($newBalanceAndPrice);
            }

            $clientRepository = new ClientRepository();
            $clientRepository->rectifyBalance($this->clientService, $newBalanceAndPrice);
            $clientRepository->addDebitTransactionForPaymentService($this->planRelation, $this->clientService, $newBalanceAndPrice,null,$this->transaction);
            ClientServiceChargedJob::dispatch($this->clientService);
        }
    }

    private function modificoYObtengoElNewBalanceAndPriceSegunLaCantidadDeVecesQueSelePuedeCobrar($newBalanceAndPrice)
    {
        // Aqui ya se cobro el servio una vez
        // Para obtener el balance final tendriamos que multiplicar el costo por la cantidad de veces que se puede cobrar, restandole a la cantidad de veces 1 que fue la que ya se cobro en este punto

        return [
            'new_balance' => $newBalanceAndPrice['new_balance'] - ($newBalanceAndPrice['cost'] * ($this->cuantasVecesSeLePuedeCobrar - 1)),
            'price' => $newBalanceAndPrice['price'],
            'cost' => $newBalanceAndPrice['cost'] * $this->cuantasVecesSeLePuedeCobrar
        ];
    }
}
