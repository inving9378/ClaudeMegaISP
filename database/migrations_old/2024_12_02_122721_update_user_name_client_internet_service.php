<?php

use App\Models\ClientInternetService;
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
        $clientInterntServices = ClientInternetService::all();
        foreach ($clientInterntServices as $service) {
            if ($service->client_name != null) {
                $service->user = $service->client_name;
            } else {
                $service->client_name = $service->user;
            }
            $service->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
