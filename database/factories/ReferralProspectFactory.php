<?php

namespace Database\Factories;

use App\Models\Referrals\ReferralProspect;
use App\Modules\Core\Clientes\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReferralProspectFactory extends Factory
{
    protected $model = ReferralProspect::class;

    public function definition(): array
    {
        return [
            'embajador_id' => Client::factory(),
            'name'         => $this->faker->name(),
            'phone'        => $this->faker->numerify('55########'),
            'email'        => $this->faker->safeEmail(),
            'status'       => 'new',
        ];
    }

    public function forEmbajador(Client $client): static
    {
        return $this->state(['embajador_id' => $client->id]);
    }

    public function withPhone(string $phone): static
    {
        return $this->state(['phone' => $phone]);
    }
}
