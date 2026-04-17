<?php

use App\Models\Client;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       $clients = Client::whereNull('fecha_pago')->get();
       foreach($clients as $client){
           $service = new \App\Services\ClientService\BillingPaymentDateService();
           $client->update(['fecha_pago' => $service->getBillingPaymentDateByTypeOfBillingOnlyForMigrationUseCuandoNingunClienteTieneUnaFechaDePago($client)]);
       }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
