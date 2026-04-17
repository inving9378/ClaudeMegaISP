<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Http\Traits\RouterConnection;
use App\Models\Interface\ServiceInterface;
use App\Services\DeployService;
use App\Services\InvoiceService;

class ProcessCreateServiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, RouterConnection;

    protected $model;
    public function __construct(ServiceInterface $model)
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
        $this->deployService();
        $this->addInvoice();
    }

    public function deployService()
    {
        $deployService = new DeployService($this->model);
        $deployService->deployService();
    }

    public function addInvoice()
    {
        $invoiceService = new InvoiceService($this->model);
        $invoiceService->addInvoice();
    }
}
