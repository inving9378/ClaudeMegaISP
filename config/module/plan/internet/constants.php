<?php

return [
    'Internet' => [
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
                'type' => 'select-component-with-checkbox',
                'value' => [],
                'search' => [
                    'model' => 'App\Models\Partner',
                    'id' => 'id',
                    'text' => 'name'
                ],
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
            'download_speed' => [
                'label' => 'Velocidad de bajada (kbps)',
                'placeholder' => 'velocidad de bajada',
                'inputGroup' => 'kbps',
                'type' => 'input-group-text',
                'value' => null,
                'position' => 9
            ],
            'upload_speed' => [
                'label' => 'Velocidad de Subida',
                'placeholder' => 'velocidad de subida',
                'inputGroup' => 'kbps',
                'type' => 'input-group-text',
                'value' => null,
                'position' => 10
            ],
            'guaranteed_speed_limit' => [
                'label' => 'Lím. Velocidad Garantizada al',
                'placeholder' => 'limite de velocidad garantizada',
                'inputGroup' => '%',
                'type' => 'input-group-number',
                'value' => null,
                'position' => 11
            ],
            'priority' => [
                'label' => 'Prioridad',
                'placeholder' => 'Seleccione la prioridad',
                'type' => 'select-component',
                'value' => null,
                'options' => ['Baja' => 'Baja', 'Normal' => 'Normal', 'Alta' => 'Alta'],
                'position' => 12
            ],
            'aggregation' => [
                'label' => 'Agregación',
                'placeholder' => 'agregacion',
                'type' => 'input-group-text-init',
                'inputGroup' => '1:',
                'value' => null,
                'position' => 13
            ],
            'burst' => [
                'label' => 'Límite de Burst',
                'placeholder' => 'burst',
                'type' => 'input-group-multiple',
                'inputGroup' => '+',
                'inputGroupEnd' => '%',
                'value' => null,
                'options' => ['Ninguno' => 'Ninguno', 'Relativo' => 'Relativo', 'Fijo' => 'Fijo'],
                'position' => 14
            ],
            'burt_umbral' => [
                'label' => 'Umbral de Burst',
                'placeholder' => 'umbral de burst',
                'type' => 'input-group-text',
                'inputGroup' => '%',
                'value' => null,
                'position' => 15
            ],
            'burt_time' => [
                'label' => 'Tiempo de Burst',
                'placeholder' => 'tiempo de burst',
                'type' => 'input-group-text',
                'inputGroup' => 'seg',
                'value' => null,
                'position' => 16
            ],
            'rates_to_change' => [
                'label' => 'Tarifas que se Pueden Elegir en Portal del Cliente',
                'placeholder' => null,
                'type' => 'select-component-with-checkbox-without-id',
                'value' => [],
                'search' => [
                    'model' => 'App\Models\Internet',
                    'id' => 'id',
                    'text' => 'title'
                ],
                'position' => 17
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
                'position' => 18
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
                'position' => 19
            ],
            'amount_days' => [
                'include' => false,
            ],
            'transaction_category' => [
                'label' => 'Categoría de la Transacción',
                'placeholder' => 'categoria de la transaccion',
                'type' => 'select-component',
                'value' => null,
                'options' => ['Servicio' => "Servicio", 'Descuento' => "Descuento", 'Pago' => "Pago", 'Reembolso' => "Reembolso", 'Corrección' => "Corrección"],
                'position' => 20
            ],
            'available_when_register_by_social_network' => [
                'label' => 'Disponible cuando se registra en la red social',
                'placeholder' => 'disponible',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 21
            ],
            'cost_activation' => [
                'type' => 'input-number',
                'value' => null,
                'label' => 'Costo de Activacion',
                'placeholder' => '',
                'default_value' => '0',
                'position' => 22
            ],
            'cost_instalation' => [
                'type' => 'input-number',
                'value' => null,
                'label' => 'Costo de Instalacion',
                'placeholder' => '',
                'default_value' => '0',
                'position' => 23
            ],
        ],
        'DATATABLE_FIELDS' => [
            'title' => [
                'name' => 'Título',
                'class' => null
            ],
            'service_name' => [
                'name' => 'Nombre del Servicio',
                'class' => null
            ],
            'price' => [
                'name' => 'Precio',
                'class' => null
            ],
            'download_speed' => [
                'name' => 'Velocidad de Descarga (kbps)',
                'class' => null
            ],
            'upload_speed' => [
                'name' => 'Velocidad de Subida (kbps)',
                'class' => null
            ],
            'update_service' => [
                'name' => 'Actualización de la descripción existente de los servicios',
                'class' => null
            ],
            'tax_include' => [
                'name' => 'Iva Incluido',
                'class' => null
            ],
            'tax' => [
                'name' => 'Iva',
                'class' => null
            ],
            'guaranteed_speed_limit' => [
                'name' => 'Lím. Velocidad Garantizada',
                'class' => null
            ],
            'priority' => [
                'name' => 'Prioridad',
                'class' => null
            ],
            'aggregation' => [
                'name' => 'Agregación',
                'class' => null
            ],
            'burst' => [
                'name' => 'Límite de Burst',
                'class' => null
            ],
            'burt_umbral' => [
                'name' => 'Umbral de Burst',
                'class' => null
            ],
            'burt_time' => [
                'name' => 'Tiempo de Burst',
                'class' => null
            ],
            'rates_to_change' => [
                'name' => 'Tarifas que se Pueden Elegir en Portal del Cliente',
                'class' => null
            ],
            'prepaid_period' => [
                'name' => 'Períodos de Pago',
                'class' => null
            ],
            'transaction_category' => [
                'name' => 'Categoría de la Transacción',
                'class' => null
            ],
            'available_when_register_by_social_network' => [
                'name' => 'Disponible cuando se registra en la red social',
                'class' => null
            ],
            'cost_activation' => [
                'name' => 'Costo de Activación',
                'class' => null
            ],
            'cost_instalation' => [
                'name' => 'Costo de Instalación',
                'class' => null
            ],
            'action' => [
                'name' => 'Acciones',
                'class' => null
            ]
        ]
    ]
];
