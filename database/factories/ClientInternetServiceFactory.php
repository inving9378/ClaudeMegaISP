<?php

namespace Database\Factories;

use App\Models\Internet;
use App\Modules\Core\Clientes\Models\Client;
use App\Modules\Core\Clientes\Models\ClientInternetService;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientInternetServiceFactory extends Factory
{
    protected $model = ClientInternetService::class;

    public function definition(): array
    {
        return [
            'client_id'   => Client::factory(),
            'internet_id' => Internet::factory(),
            'estado'      => ClientInternetService::STATE_ACTIVE,
            'pay_period'  => 'Mensual',
        ];
    }
}
