<?php

return [
    'Custom' => [
        'FIELDS' => [
            'title' => [
                'label' => 'Título',
                'placeholder' => 'título',
                'type' => 'input-string',
                'value' => null,
                'position' => 1
            ],
            'service_name' => [
                'label' => 'Nombre del Servicio',
                'placeholder' => 'nombre del servicio',
                'type' => 'input-string',
                'value' => null,
                'position' => 2
            ],
            'update_description' => [
                'label' => 'Actualizar Descripcion',
                'placeholder' => 'actualizar descripcion',
                'type' => 'input-checkbox-after-withou-validation-error',
                'hint' => 'Actualización de la descripción existente de los servicios',
                'value' => false,
                'position' => 3
            ],
            'price' => [
                'label' => 'Precio',
                'placeholder' => 'precio',
                'type' => 'input-number',
                'value' => null,
                'position' => 4
            ],
            'update_service' => [
                'label' => 'Actualizar Servicio',
                'placeholder' => 'actualizar servicio',
                'type' => 'input-checkbox-after-withou-validation-error',
                'hint' => 'Actualización de precios existentes de los servicios',
                'value' => false,
                'position' => 5
            ],
            'partners' => [
                'label' => 'Socios',
                'placeholder' => 'socios',
                'type' => 'select-component',
                'value' => null,
                'options' => [],
                'position' => 6
            ],
            'tax_include' => [
                'label' => 'IVA Incluido',
                'placeholder' => 'iva incluido',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 7
            ],
            'tax' => [
                'label' => 'IVA',
                'placeholder' => 'Seleccione IVA',
                'type' => 'select-component',
                'value' => null,
                'options' => ['0' => '0% (IVA 0%)', '16' => '16% (IVA 16%)'],
                'position' => 8
            ],
            'rates_to_change' => [
                'label' => 'Tarifas que se Pueden Elegir en Portal del Cliente',
                'placeholder' => 'Seleccione las tarifas ...',
                'type' => 'select-component-with-checkbox-without-id',
                'value' => [],
                'search' => [
                    'model' => 'App\Models\Custom',
                    'id' => 'id',
                    'text' => 'title'
                ],
                'position' => 9
            ],
            'types_of_billing' => [
                'label' => 'Tipos de Facturación',
                'placeholder' => 'facturacion',
                'type' => 'select-component-with-checkbox',
                'value' => [],
                'search' => [
                    'model' => 'App\Models\TypeBilling',
                    'id' => 'id',
                    'text' => 'type'
                ],
                'position' => 10
            ],
            'prepaid_period' => [
                'label' => 'Períodos de Pago',
                'placeholder' => 'Seleccione Períodos de Pago...',
                'value' => null,
                'options' => ['Mensual' => 'Mensual', 'Diario' => 'Grupo de Días'],
                'type' => 'select-component-with-group-inputs',
                'depend' => 'option',
                'inputs_depend' => json_encode([
                    'amount_days' => [
                        'field' => 'amount_days',
                        'label' => '# de Dias',
                        'placeholder' => '# de dias',
                        'type' => 'input-number',
                        'value' => null,
                        'depend' => 'Diario',
                        'position' => 1
                    ],
                ]),
                'position' => 11
            ],
            'amount_days' => [
                'include' => false,
            ],
            'transaction_category' => [
                'label' => 'Categoría de la Transacción',
                'placeholder' => 'Seleccione la categoría',
                'type' => 'select-component',
                'value' => null,
                'options' => ['Servicio' => "Servicio", 'Descuento' => "Descuento", 'Pago' => "Pago", 'Reembolso' => "Reembolso", 'Corrección' => "Corrección"],
                'position' => 12
            ],
            'Available_in_self_registration' => [
                'label' => 'Disponible el Auto Registro',
                'placeholder' => '',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 13
            ],

            'Bandwidth' => [
                'label' => 'Ancho de Banda',
                'placeholder' => 'Ancho de Banda',
                'type' => 'input-number',
                'value' => null,
                'position' => 14
            ],
            'Priority' => [
                'label' => 'Prioridad',
                'placeholder' => 'Seleccione la prioridad',
                'type' => 'select-component',
                'value' => null,
                'options' => ['1' => "1", '2' => "2", '3' => "3", '4' => "4", '5' => "5", '6' => "6"],
                'position' => 15
            ],
            'cost_activation' => [
                'type' => 'input-number',
                'value' => null,
                'label' => 'Costo de Activacion',
                'placeholder' => '',
                'default_value' => '0',
                'position' => 16
            ],
            'cost_instalation' => [
                'type' => 'input-number',
                'value' => null,
                'label' => 'Costo de Instalacion',
                'placeholder' => '',
                'default_value' => '0',
                'position' => 17
            ],
        ],
        'DATATABLE_FIELDS' => [
            'title' => [
                'name' => 'Título',
                'class' => null
            ],
            'price' => [
                'name' => 'Precio',
                'class' => null
            ],
            'service_name' => [
                'name' => 'Servicio',
                'class' => null
            ],
            'update_description' => [
                'name' => 'Actualización de la descripción existente de los servicios',
                'class' => null
            ],
            'partners' => [
                'name' => 'Socios',
                'class' => null
            ],
            'tax_include' => [
                'name' => 'IVA Incluido',
                'class' => null
            ],
            'tax' => [
                'name' => 'IVA',
                'class' => null
            ],
            'prepaid_period' => [
                'name' => 'Períodos de Pago',
                'class' => null
            ],
            'rates_to_change' => [
                'name' => 'Tarifas que se Pueden Elegir en Portal del Cliente',
                'class' => null
            ],
            'transaction_category' => [
                'name' => 'Categoría de la Transacción',
                'class' => null
            ],
            'available_in_self_registration' => [
                'name' => 'Disponible el Auto Registro',
                'class' => null
            ],
            'bandwidth' => [
                'name' => 'Ancho de Banda',
                'class' => null
            ],
            'priority' => [
                'name' => 'Prioridad',
                'class' => null
            ],
            'cost_activation' => [
                'name' => 'Costo de Activacion',
                'class' => null
            ],
            'cost_instalation' => [
                'name' => 'Costo de Instalacion',
                'class' => null
            ],
            'action' => [
                'name' => 'Acciones',
                'class' => null
            ]
        ],
    ],
];
