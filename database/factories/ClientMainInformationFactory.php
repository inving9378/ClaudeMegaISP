<?php

namespace Database\Factories;

use App\Modules\Core\Clientes\Models\Client;
use App\Modules\Core\Clientes\Models\ClientMainInformation;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientMainInformationFactory extends Factory
{
    protected $model = ClientMainInformation::class;

    public function definition(): array
    {
        return [
            'client_id'      => Client::factory(),
            'name'           => $this->faker->firstName(),
            'father_last_name' => $this->faker->lastName(),
            'phone'          => $this->faker->numerify('55########'),
            'estado'         => 'Activado',
        ];
    }

    public function forClient(Client $client): static
    {
        return $this->state(['client_id' => $client->id]);
    }

    public function withPhone(string $phone): static
    {
        return $this->state(['phone' => $phone]);
    }
}
