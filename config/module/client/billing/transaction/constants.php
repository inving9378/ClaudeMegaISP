<?php

return [
    'ClientTransaction' => [
        'FIELDS' => [
            'type' => [
                'label' => 'Tipo',
                'placeholder' => 'Seleccione el tipo',
                'type' => 'select-component',
                'value' => null,
                'options' => ['debit' => '+ Debit', 'credit' => '- Credit'],
                'position' => 1,
                'partition' => 'init'
            ],
            'description' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Descripcion',
                'placeholder' => '',
                'position' => 2,
                'partition' => 'init'
            ],
            'cantidad' => [
                'type' => 'input-string',
                'value' => "1",
                'label' => 'Cantidad',
                'placeholder' => '',
                'position' => 3,
                'partition' => 'init'
            ],
            'input-price-transaction' => [
                'type' => 'input-price-transaction',
                'value' => [
                    'price' => [
                        'type' => 'input-string',
                        'value' => null,
                        'label' => 'Precio',
                        'placeholder' => '',
                        'position' => 4,
                        'partition' => 'price'
                    ],
                    'iva' => [
                        'type' => 'input-string',
                        'value' => null,
                        'label' => 'Iva %',
                        'placeholder' => '',
                        'position' => 5,
                        'partition' => 'price'
                    ],
                    'withiva' => [
                        'type' => 'input-string',
                        'value' => null,
                        'label' => 'Con Iva',
                        'placeholder' => '',
                        'position' => 5,
                        'partition' => 'price'
                    ],
                    'total' => [
                        'type' => 'input-string',
                        'value' => null,
                        'label' => 'Total',
                        'placeholder' => '',
                        'position' => 5,
                        'partition' => 'price'
                    ],
                ],
                'position' => 4
            ],
            'price' => [
                'include' => false,
            ],
            'iva' => [
                'include' => false,
            ],
            'total' => [
                'include' => false,
            ],
            'period' => [
                'type' => 'data-range-picker',
                'value' => null,
                'label' => 'Periodo',
                'placeholder' => 'periodo',
                'position' => 5
            ],
            'category' => [
                'label' => 'Categoria',
                'placeholder' => 'Seleccione la categoria',
                'type' => 'select-component',
                'value' => null,
                'options' => ['Servicio' => 'Servicio', 'Descuento' => 'Descuento', 'Pago' => 'Pago', 'Reembolso' => 'Reembolso', 'Correccion' => 'Correccion'],
                'position' => 6
            ],

            'date' => [
                'type' => 'date-time-local',
                'value' => null,
                'label' => 'Fecha',
                'placeholder' => '',
                'position' => 7,
                'disabled' => true,
                'default_value' => Carbon\Carbon::now()->toDateTimeString()
            ],
            'comment' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Comentario',
                'placeholder' => 'comentario',
                'position' => 8
            ],
            'add_to_invoice' => [
                'label' => 'Add to Invoice',
                'placeholder' => 'add-to-invoice',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 9
            ]
        ],
        'DATATABLE_FIELDS' => [
            'date' => [
                'name' => 'Fecha',
                'class' => null
            ],
            'debit' => [
                'name' => '+ Debito',
                'class' => null
            ],
            'credit' => [
                'name' => 'Credito',
                'class' => null
            ],
            'account_balance' => [
                'name' => 'Account Balance',
                'class' => null
            ],
            'type' => [
                'name' => 'Tipo',
                'class' => null
            ],
            'description' => [
                'name' => 'Descripcion',
                'class' => null
            ],
            'cantidad' => [
                'name' => 'Cantidad',
                'class' => null
            ],
            'price' => [
                'name' => 'Precio',
                'class' => null
            ],
            'category' => [
                'name' => 'Categoria',
                'class' => null
            ],
            'client_id' => [
                'name' => 'client_id',
                'class' => null
            ],
            'iva' => [
                'name' => 'Iva',
                'class' => null
            ],
            'total' => [
                'name' => 'Total',
                'class' => null
            ],
            'from_date' => [
                'name' => 'Desde fecha',
                'class' => null
            ],
            'to_date' => [
                'name' => 'Hasta Fecha',
                'class' => null
            ],
            'comment' => [
                'name' => 'Comentario',
                'class' => null
            ],
            'period' => [
                'name' => 'Periodo',
                'class' => null
            ],
            'add_to_invoice' => [
                'name' => 'Agragar factura',
                'class' => null
            ],
            'movement' => [
                'name' => 'Movimiento',
                'class' => null
            ],
            'service_name' => [
                'name' => 'Servicio',
                'class' => null
            ],
            'invoice' => [
                'name' => 'Factura',
                'class' => null
            ],
            'transactionable_id' => [
                'name' => 'transactionable_id',
                'class' => null
            ],
            'transactionable_type' => [
                'name' => 'transactionable_type',
                'class' => null
            ],
            'is_payment' => [
                'name' => 'Pagada',
                'class' => null
            ],
            'payment_id' => [
                'name' => 'payment_id',
                'class' => null
            ],
            'action' => [
                'name' => 'Acciones',
                'class' => null
            ]
        ],
    ],
];
