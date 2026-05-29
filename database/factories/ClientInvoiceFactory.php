<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Core\Clientes\Models\Client;
use App\Modules\Core\Clientes\Models\ClientInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientInvoiceFactory extends Factory
{
    protected $model = ClientInvoice::class;

    public function definition(): array
    {
        $admin = User::factory()->create();

        return [
            'number'        => $this->faker->unique()->numberBetween(10000, 999999),
            'total'         => 299.00,
            'estado'        => 'Pagado',
            'client_id'     => Client::factory(),
            'added_by'      => $admin->id,
            'document_date' => now()->format('Y-m-d'),
            'created_at'    => now()->toDateTimeString(),
        ];
    }

    public function total(float $amount): static
    {
        return $this->state(['total' => $amount]);
    }

    public function forClient(Client $client): static
    {
        return $this->state(['client_id' => $client->id]);
    }
}
