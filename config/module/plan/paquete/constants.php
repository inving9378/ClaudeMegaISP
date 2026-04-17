<?php

return [
    'Bundle' => [
        'FIELDS' => [
            'title' => [
                'label' => 'Título',
                'placeholder' => 'título',
                'type' => 'input-string',
                'value' => null,
                'position' => 1
            ],
            'service_description' => [
                'label' => 'Descripción del Servicio',
                'placeholder' => 'Descripción',
                'type' => 'input-string',
                'value' => null,
                'position' => 2
            ],
            'price' => [
                'label' => 'Precio',
                'placeholder' => 'precio',
                'type' => 'input-number',
                'value' => null,
                'position' => 3
            ],
            'tax_include' => [
                'label' => 'IVA Incluido',
                'placeholder' => 'iva incluido',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 4
            ],
            'tax' => [
                'label' => 'IVA',
                'placeholder' => 'Seleccione IVA',
                'type' => 'select-component',
                'value' => null,
                'options' => ['0' => '0% (IVA 0%)', '16' => '16% (IVA 16%)'],
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
                'position' => 7
            ],
            'transaction_category' => [
                'label' => 'Categoría de la Transacción',
                'placeholder' => 'categoria de la transaccion',
                'type' => 'select-component',
                'value' => null,
                'options' => ['Servicio' => "Servicio", 'Descuento' => "Descuento", 'Pago' => "Pago", 'Reembolso' => "Reembolso", 'Corrección' => "Corrección"],
                'position' => 8
            ],
            'activation_fee' => [
                'label' => 'Costo de Activación',
                'placeholder' => '0.000',
                'type' => 'input-number',
                'value' => null,
                'position' => 9
            ],
            'get_activation_fee_when' => [
                'label' => 'Aplica activación',
                'placeholder' => 'Aplica activación',
                'type' => 'select-component',
                'value' => null,
                'options' => [
                    'En facturación del primer servicio' => 'En facturación del primer servicio',
                    'Al crear el servicio' => 'Al crear el servicio'
                ],
                'position' => 10
            ],
            'emit_invoice' => [
                'label' => 'Emitir Factura',
                'placeholder' => 'Emitir Factura',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 11
            ],
            'contract_duration' => [
                'label' => 'Duración del Contrato',
                'placeholder' => '0.000',
                'type' => 'input-number',
                'value' => null,
                'position' => 12
            ],
            'automatic_renewal' => [
                'label' => 'Renovación Automática',
                'placeholder' => '',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 13
            ],
            'auto_reactivate' => [
                'label' => 'Reactivar automáticamente ',
                'placeholder' => '',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 14
            ],
            'cancellation_fee' => [
                'label' => 'Cancelación previa',
                'placeholder' => '0.000',
                'type' => 'input-number',
                'value' => null,
                'position' => 15
            ],
            'prior_cancellation_fee' => [
                'label' => 'Tarifa de Cancelación Previa',
                'placeholder' => '0.000',
                'type' => 'input-number',
                'value' => null,
                'position' => 16
            ],
            'discount_period' => [
                'label' => 'Período de Descuento',
                'placeholder' => '0.000',
                'type' => 'input-number',
                'value' => null,
                'position' => 17
            ],
            'discount_value' => [
                'label' => 'Porciento de Descuento',
                'placeholder' => '0.0',
                'type' => 'input-number',
                'value' => null,
                'position' => 18
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
            'service_description' => [
                'name' => 'Descripción',
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
            'transaction_category' => [
                'name' => 'Categoría de la Transacción',
                'class' => null
            ],
            'activation_fee' => [
                'name' => 'Costo de Activación',
                'class' => null
            ],
            'get_activation_fee_when' => [
                'name' => 'Aplica activación',
                'class' => null
            ],
            'emit_invoice' => [
                'name' => 'Emitir Factura',
                'class' => null
            ],
            'contract_duration' => [
                'name' => 'Duración del Contrato',
                'class' => null
            ],
            'automatic_renewal' => [
                'name' => 'Renovación Automática',
                'class' => null
            ],
            'auto_reactivate' => [
                'name' => 'Reactivar automáticamente',
                'class' => null
            ],
            'cancellation_fee' => [
                'name' => 'Cancelación previa',
                'class' => null
            ],
            'prior_cancellation_fee' => [
                'name' => 'Tarifa de Cancelación Previa',
                'class' => null
            ],
            'discount_period' => [
                'name' => 'Período de Descuento',
                'class' => null
            ],
            'discount_value' => [
                'name' => 'Porciento de Descuento',
                'class' => null
            ],
            'action' => [
                'name' => 'Acciones',
                'class' => null
            ]
        ],
    ],
    'BundleLeft' => [
        'FIELDS' => [
            'title' => [
                'label' => 'Título',
                'placeholder' => 'título',
                'type' => 'input-string',
                'value' => null,
                'position' => 1
            ],
            'service_description' => [
                'label' => 'Descripción del Servicio',
                'placeholder' => 'Descripción',
                'type' => 'input-string',
                'value' => null,
                'position' => 2
            ],
            'price' => [
                'label' => 'Precio',
                'placeholder' => 'precio',
                'type' => 'input-number',
                'value' => null,
                'position' => 3
            ],
            'tax_include' => [
                'label' => 'IVA Incluido',
                'placeholder' => 'iva incluido',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 4
            ],
            'tax' => [
                'label' => 'IVA',
                'placeholder' => 'Seleccione IVA',
                'type' => 'select-component',
                'value' => null,
                'options' => ['0' => '0% (IVA 0%)', '16' => '16% (IVA 16%)'],
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
                'position' => 7
            ],
            'transaction_category' => [
                'label' => 'Categoría de la Transacción',
                'placeholder' => 'categoria de la transaccion',
                'type' => 'select-component',
                'value' => null,
                'options' => ['Servicio' => "Servicio", 'Descuento' => "Descuento", 'Pago' => "Pago", 'Reembolso' => "Reembolso", 'Corrección' => "Corrección"],
                'position' => 8
            ],
        ]
    ],
    'BundleRight' => [
        'FIELDS' => [
            'activation_fee' => [
                'label' => 'Costo de Activación',
                'placeholder' => '0.000',
                'type' => 'input-number',
                'value' => null,
                'position' => 1
            ],
            'get_activation_fee_when' => [
                'label' => 'Aplica activación',
                'placeholder' => 'Aplica activación',
                'type' => 'select-component',
                'value' => null,
                'options' => [
                    'En facturación del primer servicio' => 'En facturación del primer servicio',
                    'Al crear el servicio' => 'Al crear el servicio'
                ],
                'position' => 2
            ],
            'emit_invoice' => [
                'label' => 'Emitir Factura',
                'placeholder' => 'Emitir Factura',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 3
            ],
            'contract_duration' => [
                'label' => 'Duración del Contrato',
                'placeholder' => '0.000',
                'type' => 'input-number',
                'value' => null,
                'position' => 4
            ],
            'automatic_renewal' => [
                'label' => 'Renovación Automática',
                'placeholder' => '',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 5
            ],
            'auto_reactivate' => [
                'label' => 'Reactivar automáticamente ',
                'placeholder' => '',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 6
            ],
            'cancellation_fee' => [
                'label' => 'Cancelación previa',
                'placeholder' => '0.000',
                'type' => 'input-number',
                'value' => null,
                'position' => 7
            ],
            'prior_cancellation_fee' => [
                'label' => 'Tarifa de Cancelación Previa',
                'placeholder' => '0.000',
                'type' => 'input-number',
                'value' => null,
                'position' => 8
            ],
            'discount_period' => [
                'label' => 'Período de Descuento',
                'placeholder' => '0.000',
                'type' => 'input-number',
                'value' => null,
                'position' => 9
            ],
            'discount_value' => [
                'label' => 'Porciento de Descuento',
                'placeholder' => '0.0',
                'type' => 'input-number',
                'value' => null,
                'position' => 10
            ],
        ],
    ],
    'BundleBottom' => [
        'FIELDS' => [
            'planes_internet' => [
                'label' => 'Internet',
                'placeholder' => 'Agregar Plan',
                'type' => 'select-single-add-items',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\Internet',
                    'id' => 'id',
                    'text' => 'title'
                ],
                'position' => 0
            ],
            'planes_voz' => [
                'label' => 'Voz',
                'placeholder' => 'Agregar Plan',
                'type' => 'select-single-add-items',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\Voise',
                    'id' => 'id',
                    'text' => 'title'
                ],
                'position' => 1
            ],
            'planes_custom' => [
                'label' => 'Custom',
                'placeholder' => 'Agregar Plan',
                'type' => 'select-single-add-items',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\Custom',
                    'id' => 'id',
                    'text' => 'title'
                ],
                'position' => 2
            ]
        ],
    ],
];
