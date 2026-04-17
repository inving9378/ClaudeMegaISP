<?php

namespace Database\Seeders;

use App\Http\Controllers\Module\Client\ClientController;
use App\Http\HelpersModule\module\client\ClientDatatableHelper;
use App\Models\Helper\Client as ClientRequest;
use App\Services\UserAuthenticator;
use Illuminate\Database\Seeder;

class ClientTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->environment('testing')) {
            $userAuthenticator = new UserAuthenticator();
            $userAuthenticator->simulate();
        }
    }
}
