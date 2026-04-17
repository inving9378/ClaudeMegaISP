<?php

namespace App\Jobs;

use App\Http\Controllers\Utils\TypeOfBillingController;
use App\Http\Repository\ClientRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Http\Traits\RouterConnection;
use App\Models\ClientBundleService;
use App\Models\ClientInternetService;
use App\Services\ClientMainInformationService;
use Illuminate\Support\Facades\Log;

class SuspendServiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, RouterConnection;

    protected $model;
    public function __construct($model)
    {
        $this->model = $model;
    }
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // suspende servicio
        $repository =  $this->model->getRepository();
        $serviceRepository = new $repository();
        $serviceRepository->suspendService($this->model);
    }
}
