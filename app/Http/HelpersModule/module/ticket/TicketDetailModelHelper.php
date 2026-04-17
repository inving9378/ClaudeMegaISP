<?php

namespace App\Http\HelpersModule\module\ticket;

class TicketDetailModelHelper {

    const FIELDS = [
        'cliente' => [
            'type' => 'select',
            'value' => null,
            'search' => [
                'model' => 'App\Models',
                'id' => 'id',
                'text' => 'name'
            ]
        ]
    ];
}
