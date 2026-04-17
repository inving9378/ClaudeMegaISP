<?php

return [
    'ClientInvoice' => [
        'FIELDS' => [
            'number' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Fecha',
                'placeholder' => '00/00/00',
                'position' => 3
            ],
            'pay_up' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Fecha',
                'placeholder' => '00/00/00',
                'position' => 3
            ],
            'use_of_transactions' => [
                'label' => 'Uso de transacciones',
                'placeholder' => 'Uso de transacciones',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 7
            ],
            'note' => [
                'label' => 'Nota',
                'placeholder' => 'Nota para el Cliente',
                'type' => 'input-text-area',
                'value' => null,
                'position' => 9
            ],
            'memo' => [
                'label' => 'Memo',
                'placeholder' => 'Memo para usted',
                'type' => 'input-text-area',
                'value' => null,
                'position' => 9
            ],

        ],
        'DATATABLE_FIELDS' => [
            'number' => [
                'name' => 'Número',
                'class' => null
            ],
            'created_at' => [
                'name' => 'Fecha',
                'class' => null
            ],
            'total' => [
                'name' => 'Total',
                'class' => null
            ],
            'payment_date' => [
                'name' => 'Fecha de Pago',
                'class' => null
            ],
            'estado' => [
                'name' => 'Estado',
                'class' => null
            ],
            'last_update' => [
                'name' => 'Última Actualización',
                'class' => null
            ],
            'pay_up' => [
                'name' => 'Pagar',
                'class' => null
            ],
            'use_of_transactions' => [
                'name' => 'Uso de transaccion',
                'class' => null
            ],
            'note' => [
                'name' => 'Nota',
                'class' => null
            ],
            'memo' => [
                'name' => 'memo',
                'class' => null
            ],
            'payment' => [
                'name' => 'Pagado',
                'class' => null
            ],
            'is_sent' => [
                'name' => 'Enviado',
                'class' => null
            ],
            'delete_transactions' => [
                'name' => 'Transaccion Eliminada',
                'class' => null
            ],
            'added_by' => [
                'name' => 'Agregado por',
                'class' => null
            ],
            'type' => [
                'name' => 'Tipo',
                'class' => null
            ],
            'action' => [
                'name' => 'Acciones',
                'class' => null
            ]
        ],
    ],
];
