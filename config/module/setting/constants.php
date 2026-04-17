<?php

return [
    'SettingDebtPaymentClientRecurrent' => [
        'FIELDS' => [
            'apply_discount' => [
                'label' => 'Aplicar descuento',
                'placeholder' => 'aplicar-descuento',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 1
            ],
            'percent_discount' => [
                'type' => 'input-number',
                'value' => null,
                'label' => 'Porciento de Descuento',
                'placeholder' => '',
                'position' => 2
            ],
            'apply_group_of_days' => [
                'type' => 'input-number',
                'value' => null,
                'label' => 'Aplicable en grupo de dias',
                'placeholder' => '',
                'position' => 3
            ],
        ],
    ],
    'SettingDebtPaymentClientCustom' => [
        'FIELDS' => [
            'percent_discount' => [
                'type' => 'input-number',
                'value' => null,
                'label' => 'Porciento de Descuento',
                'placeholder' => '',
                'position' => 1
            ],
            'apply_group_of_months' => [
                'type' => 'input-number',
                'value' => null,
                'label' => 'Meses',
                'placeholder' => '',
                'position' => 2
            ],
        ],
        'DATATABLE_FIELDS' => [
            'percent_discount' => [
                'name' => 'Porciento',
                'class' => null
            ],
            'apply_group_of_months' => [
                'name' => 'Meses',
                'class' => null
            ],
            'action' => [
                'name' => 'Acciones',
                'class' => null
            ]
        ]
    ]
];
