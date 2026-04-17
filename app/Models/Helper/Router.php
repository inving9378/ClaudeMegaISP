<?php

namespace App\Models\Helper;

use App\Http\Requests\module\router\RouterCreateRequest;

class Router
{
    public static function router1(): RouterCreateRequest
    {
        // Create a new request
        $request = new RouterCreateRequest();

        // Mock expected request data
        $request_data = [
            'title' => 'Router 1',
            'ip_host' => '192.168.1.1',
        ];

        return $request->merge($request_data);
    }
    public static function router2(): RouterCreateRequest
    {
        // Create a new request
        $request = new RouterCreateRequest();

        // Mock expected request data
        $request_data = [
            'title' => 'Router 2',
            'ip_host' => '192.168.2.1',
        ];

        return $request->merge($request_data);
    }
}
