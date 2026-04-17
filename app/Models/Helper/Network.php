<?php

namespace App\Models\Helper;

use App\Http\Requests\module\network\NetworkCreateRequest;

class Network
{
    public static function network1(): NetworkCreateRequest
    {
        // Create a new request
        $request = new NetworkCreateRequest();

        // Mock expected request data
        $request_data = [
            'title' => 'Network 1',
            'bm' => '100',
            'comment' => 'comment 1',
            'network' => '192.168.1.0',
            'type_of_use' => 'EndNet',
            'router_id' => 1,
        ];

        return $request->merge($request_data);
    }
    public static function network2(): NetworkCreateRequest
    {
        // Create a new request
        $request = new NetworkCreateRequest();

        // Mock expected request data
        $request_data = [
            'title' => 'Network 2',
            'bm' => '100',
            'comment' => 'comment 2',
            'network' => '192.168.2.0',
            'type_of_use' => 'EndNet',
            'router_id' => 1,
        ];

        return $request->merge($request_data);
    }
}
