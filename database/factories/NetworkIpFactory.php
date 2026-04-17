<?php

namespace Database\Factories;

use App\Models\NetworkIp;
use Illuminate\Database\Eloquent\Factories\Factory;

class NetworkIpFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = NetworkIp::class;

    public function definition()
    {
        $networks = [
            'id' => 1385,
            'ip' => '10.10.8.3',
            'network_id' => 38,
            'used' => 0,
            'used_by' => '--',
            'title' => NULL,
            'hostname' => NULL,
            'location_id' => '0',
            'host_category' => 'Ninguno',
            'ping' => NULL,
            'comment' => NULL,
            'client_id' => NULL,
            'type_service' => NULL,


        ];
        return $networks;
    }

    public function staticNetwork1()
    {
        return $this->state([
            'id' => 1385,
            'ip' => '10.10.8.3',
            'network_id' => 38,
            'used' => 0,
            'used_by' => '--',
            'title' => null,
            'hostname' => null,
            'location_id' => '0',
            'host_category' => 'Ninguno',
            'ping' => null,
            'comment' => null,
            'client_id' => null,
            'type_service' => null,
        ]);
    }

    public function staticNetwork2()
    {
        return $this->state([
            'id' => 1409,
            'ip' => '10.10.8.26',
            'network_id' => 38,
            'used' => 0,
            'used_by' => '--',
            'title' => null,
            'hostname' => null,
            'location_id' => '0',
            'host_category' => 'Ninguno',
            'ping' => null,
            'comment' => null,
            'client_id' => null,
            'type_service' => null,
        ]);
    }

}
