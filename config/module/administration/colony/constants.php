<?php

return [
    'Colony' => [
        'FIELDS' => [
            'name' => [
                'label' => 'Nombre',
                'placeholder' => 'Nombre',
                'type' => 'input-string',
                'value' => null,
                'position' => 1
            ],
            'municipality_id' => [
                'label' => 'Municipio',
                'placeholder' => 'Seleccionar Municipio',
                'type' => 'select-2-component',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\Municipality',
                    'id' => 'id',
                    'text' => 'name'
                ],
                'position' => 2
            ],

        ],
        'DATATABLE_FIELDS' => [
            'name' => [
                'name' => 'Nombre',
                'class' => null
            ],
            'municipality_id=' => [
                'name' => 'Municipio',
                'class' => null
            ],
            'action' => [
                'name' => 'Acciones',
                'class' => null
            ]
        ]
    ],
];
