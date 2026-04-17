<?php

namespace App\Http\HelpersModule\module\administration\rol;

class RolModelHelper
{
    const FIELDS = [
        'name' => [
            'type' => 'string',
            'value' => null,
            'label' => 'Nombre',
            'placeholder' => 'nombre'
        ],
        'permissions' => [
            'type' => 'select',
            'value' => [],
            'search' => [
                'model' => 'Spatie\Permission\Models\Permission',
                'id' => 'id',
                'text' => 'name'
            ]
        ]
    ];

    const DATATABLE_FIELDS = [
        'name' => [
            'name' => 'Nombre',
            'class' => null
        ],
        'action' => [
            'name' => 'Acciones',
            'class' => null
        ]
    ];

    const MULTIPLE_RELATIONS = [
        'permissions' => 'permissions'
    ];
}
