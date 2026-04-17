<?php

return [
    'ClientPayment' => [
        'FIELDS' => [
            'payment_method_id' => [
                'label' => 'Metodo de pago',
                'placeholder' => 'Seleccione el metodo de pago...',
                'type' => 'select-component',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\MethodOfPayment',
                    'id' => 'id',
                    'text' => 'type'
                ],
                'default_value' => 1,
                'position' => 1
            ],
            'amount' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Cantidad',
                'placeholder' => 'cantidad',
                'position' => 3
            ],
            'receipt' => [
                'type' => 'input-string',
                'value' => null,
                'label' => '# de Recibo',
                'placeholder' => '',
                'disabled' => true,
                'default_value' => [
                    'request' => '/cliente/get-receipt-for-client',
                ],
                'position' => 4
            ],
            'payment_period' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Periodo de pago',
                'placeholder' => 'periodo-de-pago',
                'default_value' => [
                    'request' => '/get-payment-period',
                ],
                'position' => 5
            ],
            'date_payment' => [
                'type' => 'input-string-information',
                'value' => null,
                'label' => 'Fecha de Pago',
                'placeholder' => 'fecha',
                'default_value' => [
                    'request' => '/get-default-value/now-show',
                ],
                'position' => 5
            ],
            'comment' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Comentario',
                'placeholder' => 'comentario',
                'position' => 6
            ],
            'file' => [
                'type' => 'input-file',
                'value' => null,
                'label' => 'Ticket',
                'placeholder' => '',
                'position' => 7
            ],
            'send_receipt_after_payment' => [
                'label' => 'Enviar Recibo después del Pago',
                'placeholder' => '',
                'type' => 'input-checkbox',
                'value' => true,
                'position' => 8
            ],
            'enabled_payment_promise' => [
                'partition' => 'init',
                'label' => 'Promesa de Pago',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'first_court_date' => [
                        'field' => 'first_court_date',
                        'type' => 'date-time-local',
                        'value' => null,
                        'label' => 'Primer corte',
                        'placeholder' => 'Fecha hasta',
                        'default_value' => 'now',
                        'position' => 1
                    ],
                    'first_amount' => [
                        'field' => 'first_amount',
                        'type' => 'input-number',
                        'value' => null,
                        'label' => 'Primer monto',
                        'placeholder' => '0.0',
                        'position' => 2
                    ],
                    'second_court_date' => [
                        'field' => 'second_court_date',
                        'type' => 'date-time-local',
                        'value' => null,
                        'label' => 'Segundo corte',
                        'placeholder' => 'Fecha hasta',
                        'default_value' => 'now',
                        'position' => 3
                    ],
                    'second_amount' => [
                        'field' => 'second_amount',
                        'type' => 'input-number',
                        'value' => null,
                        'label' => 'Segundo monto',
                        'placeholder' => '0.0',
                        'position' => 4
                    ],
                    'third_court_date' => [
                        'field' => 'third_court_date',
                        'type' => 'date-time-local',
                        'value' => null,
                        'label' => 'Tercer corte',
                        'placeholder' => 'Fecha hasta',
                        'default_value' => 'now',
                        'position' => 5
                    ],
                    'third_amount' => [
                        'field' => 'third_amount',
                        'type' => 'input-number',
                        'value' => null,
                        'label' => 'Tercer monto',
                        'placeholder' => '0.0',
                        'position' => 6
                    ],

                ]),
                'position' => 9
            ],
            'first_court_date' => [
                'type' => 'depend-field',
            ],
            'first_amount' => [
                'type' => 'depend-field',
            ],
            'second_court_date' => [
                'type' => 'depend-field',
            ],
            'second_amount' => [
                'type' => 'depend-field',
            ],
            'third_court_date' => [
                'type' => 'depend-field',
            ],
            'third_amount' => [
                'type' => 'depend-field',
            ],
        ],
        'DATATABLE_FIELDS' => [
            'date' => [
                'name' => 'Fecha de Pago',
                'class' => null
            ],
            'payment_method_id' => [
                'name' => 'Forma de Pago',
                'class' => null
            ],
            'amount' => [
                'name' => 'Cantidad',
                'class' => null
            ],
            'payment_period' => [
                'name' => 'Periodo',
                'class' => null
            ],
            'comment' => [
                'name' => 'Comentario',
                'class' => null
            ],
            'receipt' => [
                'name' => 'Recibo',
                'class' => null
            ],
            'send_receipt_after_payment' => [
                'name' => 'Enviar recivo despues del pago',
                'class' => null
            ],
            'add_by' => [
                'name' => 'Agregado por',
                'class' => null
            ],
            'paymentable_id' => [
                'name' => 'paymentable_id',
                'class' => null
            ],
            'paymentable_type' => [
                'name' => 'paymentable_type',
                'class' => null
            ],
            'action' => [
                'name' => 'Acciones',
                'class' => null
            ]
        ],
    ],
];
