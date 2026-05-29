<?php

namespace Database\Factories;

use App\Models\Internet;
use Illuminate\Database\Eloquent\Factories\Factory;

class InternetFactory extends Factory
{
    protected $model = Internet::class;

    public function definition(): array
    {
        return [
            'title'                   => $this->faker->words(3, true),
            'service_name'            => $this->faker->word(),
            'price'                   => 299.00,
            'tax_include'             => 0,
            'tax'                     => 0,
            'download_speed'          => 102400, // 100 Mbps en Kbps
            'upload_speed'            => 20480,  // 20 Mbps en Kbps
            'guaranteed_speed_limit'  => 10240,
            'priority'                => 'Normal',
            'aggregation'             => 1,
            'burst'                   => 0,
        ];
    }

    public function speedKbps(int $kbps): static
    {
        return $this->state(['download_speed' => $kbps]);
    }
}
