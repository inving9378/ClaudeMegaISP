<?php

return [
    'MethodOfPayment' => [
        'FIELDS' => [
            'type' => [
                'label' => 'Nombre',
                'placeholder' => 'Nombre',
                'type' => 'input-string',
                'value' => null,
                'position' => 1
            ],
             'active' => [
                'label' => 'Activo',
                'placeholder' => 'Activo',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 2
            ],
        ],
        'DATATABLE_FIELDS' => [
            'type' => [
                'name' => 'Nombre',
                'class' => null
            ],
            'active' => [
                'name' => 'Activo',
                'class' => null
            ],
            'action' => [
                'name' => 'Acciones',
                'class' => null
            ]
        ]
    ],
];
