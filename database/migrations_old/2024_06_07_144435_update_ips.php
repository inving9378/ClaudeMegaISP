<?php

use App\Jobs\NetworkIp\SetIPToClientInternetServiceJob;
use App\Models\Client;
use App\Models\NetworkIp;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0'); // Deshabilita restricciones de clave externa

        foreach (NetworkIp::all() as $value) {
            $value->update(['used' => false, 'used_by' => '--', 'client_id' => null, 'type_service' => null]);
        }

        $clients = Client::with(['client_main_information', 'client_additional_information', 'internet_service'])->whereHas('internet_service')->get();
        foreach ($clients as $client) {
            foreach ($client->internet_service as $service) {
                SetIPToClientInternetServiceJob::dispatch($service);
            }
        }





        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
