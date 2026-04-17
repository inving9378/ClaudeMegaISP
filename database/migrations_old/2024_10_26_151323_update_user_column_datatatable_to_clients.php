<?php

use App\Models\ClientInternetService;
use App\Models\Module;
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
        $module = Module::where('name', 'Client')->first();
        $module->columnsDatatable()->where('name', 'user')->delete();
        $module->columnsDatatable()->where('name', 'service_user_name')->first()->update(['filter_name' => 'client_internet_services.user', 'label' => 'Usuario Internet']);
        $clientInternetServices = ClientInternetService::all();
        foreach ($clientInternetServices as $service) {
            if ($service->client_name != $service->user) {
                $service->user = $service->client_name;
                $service->save();
            }
        }

        $columnsDatatableToDelete = ['voz_price', 'custom_price', 'recurrent_price', 'internet_services_start_date', 'internet_services_end_date', 'voice_services_start_date', 'voice_services_end_date', 'custom_services_start_date', 'custom_services_end_date', 'recurring_services_start_date', 'recurring_services_end_date'];
        foreach ($columnsDatatableToDelete as $columnName) {
            $module->columnsDatatable()->where('name', $columnName)->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'Client')->first();
        $module->columnsDatatable()->where('name', 'service_user_name')->first()->update(['filter_name' => null, 'label' => 'Servicio Usuario']);
    }
};
