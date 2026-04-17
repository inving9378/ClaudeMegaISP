<?php

namespace Database\Seeders;

use App\Services\NetworkIpService;
use App\Services\NetworkService;
use Illuminate\Database\Seeder;

class NetworkTestSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('testing')) {
            $networkService = new NetworkService();
            $networkService->simulate();

            $networkIpService = new NetworkIpService();
            $networkIpService->simulate();
        }
    }

}
