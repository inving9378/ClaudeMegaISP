<?php

return [
    'Municipality' => [
        'FIELDS' => [
            'name' => [
                'label' => 'Nombre',
                'placeholder' => 'Nombre',
                'type' => 'input-string',
                'value' => null,
                'position' => 1
            ],
            'state_id' => [
                'label' => 'Estado',
                'placeholder' => 'Seleccionar el Estado',
                'type' => 'select-2-component',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\State',
                    'id' => 'id',
                    'text' => 'name'
                ],
                'position' => 2,
            ],
        ],
        'DATATABLE_FIELDS' => [
            'name' => [
                'name' => 'Nombre',
                'class' => null
            ],
            'state_id' => [
                'name' => 'Estado',
                'class' => null
            ],
            'action' => [
                'name' => 'Acciones',
                'class' => null
            ]
        ]
    ],
];
