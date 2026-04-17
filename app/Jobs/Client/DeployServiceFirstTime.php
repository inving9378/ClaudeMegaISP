<?php

namespace App\Jobs\Client;

use App\Http\Repository\ClientInternetServiceRepository;
use App\Http\Repository\ClientMainInformationRepository;
use App\Services\ClientMainInformationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeployServiceFirstTime implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $clientService;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($clientService)
    {
        $this->clientService = $clientService;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $clientInternetServiceRepository = new ClientInternetServiceRepository();
        $clientInternetServiceRepository->setDeployedTrueAndActiveService($this->clientService);        

        $service = new ClientMainInformationService($this->clientService->client->id);
        $service->setStateActive();
    }
}
