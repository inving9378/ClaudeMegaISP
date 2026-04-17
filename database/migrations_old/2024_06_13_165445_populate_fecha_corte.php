<?php

use App\Services\ClientService\BillingExpirationService;
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
        $clients = \App\Models\Client::with('client_main_information', 'billing_configuration')
            ->whereHas('client_main_information')
            ->whereHas('billing_configuration')
            ->whereHas('transactions')
            ->whereNull('fecha_corte')
            ->get();
        foreach ($clients as $client) {
            $billingExpirationService = new BillingExpirationService($client);
            $fecha = $billingExpirationService->getBillingExpirationByTypeOfBillingOnlyForMigrationUseCuandoNingunClienteTieneUnaFechaDeCorte();
            $client->update(['fecha_corte' => $fecha]);
        }

        \App\Models\User::where('email', 'admin@admin.com')->update(['password' => base64_encode('inving2024.-')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
