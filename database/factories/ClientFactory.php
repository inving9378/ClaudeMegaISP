<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Core\Clientes\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        $user = User::factory()->create();

        return [
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];
    }
}
