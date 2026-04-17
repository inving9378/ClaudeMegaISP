<?php

namespace Database\Factories;

use App\Models\Network;
use Illuminate\Database\Eloquent\Factories\Factory;

class NetworkFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Network::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
           'id' => 38,
           'network' => '10.10.0.0',
           'bm' => 19,
           'rootnet' => NULL,
           'used' => NULL,
           'title' => 'FIBRA_CCR1009',
           'network_type' => 'EndNet',
           'network_category' => 'Produccion',
           'comment' => 'FIBRA_CCR1009',
           'location_id' => NULL,
           'router_id' => '2',
           'type_of_use' => 'Estatico',
           'allow_usage_network' => '0',
           'parent_id' => NULL,
           'deployed' => '0',
        ];
    }

}
