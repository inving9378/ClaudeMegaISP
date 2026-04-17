<?php

return [
    'ClientBillingConfigurationCustom' => [
        'FIELDS' => [
            'payment_method_id' => [
                'label' => 'Metodo de pago',
                'placeholder' => 'metodo-de-pago',
                'type' => 'select-component',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\MethodOfPayment',
                    'id' => 'id',
                    'text' => 'type'
                ],
                'position' => 1
            ],
            'minimum_balance' => [
                'type' => 'input-number',
                'value' => null,
                'label' => 'Balance minimo',
                'placeholder' => 'balance-minimo',
                'default_value' => '0',
                'position' => 2
            ],
            'create_monthly_invoice' => [
                'label' => 'Crear facturas (mensual)',
                'placeholder' => 'crear-facturas-mensual',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 3
            ],
            'send_financial_notification' => [
                'label' => 'Enviar notificaciones de finanzas',
                'placeholder' => 'enviar-notificaciones-de-finanzas',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 11
            ],

        ],
    ],
    'ClientBillingConfigurationRecurrent' => [
        'FIELDS' => [
            'billing_activated' => [
                'label' => 'Activar Facturacion',
                'placeholder' => 'activar-facturacion',
                'type' => 'input-checkbox',
                'default_value' => '1',
                'value' => false,
                'position' => 1
            ],
            'period' => [
                'type' => 'input-number',
                'value' => null,
                'label' => 'Periodo',
                'placeholder' => '',
                'default_value' => 1,
                'position' => 2
            ],
            'payment_method_id' => [
                'label' => 'Metodo de pago',
                'placeholder' => 'metodo-de-pago',
                'type' => 'select-component',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\MethodOfPayment',
                    'id' => 'id',
                    'text' => 'type'
                ],
                'position' => 3
            ],
            'billing_date' => [
                'type' => 'input-number',
                'value' => null,
                'label' => 'Dia de facturación',
                'placeholder' => 'dia-de-facturacion',
                'default_value' => ["request" => '/get-default-billing-date-for-client'],
                'position' => 4
            ],
            'billing_expiration' => [
                'type' => 'input-number',
                'value' => null,
                'label' => 'Bloqueo de servicio',
                'placeholder' => 'dias de servicio sin pagar',
                'options' => [
                    'min' => 1,
                    'max' => 31
                ],
                'default_value' => ["request" => '/get-default-billing-date-for-client'],
                'position' => 5
            ],
            'grace_period' => [
                'type' => 'input-number',
                'value' => null,
                'label' => 'Grace periodo',
                'placeholder' => 'grace periodo',
                'default_value' => '90',
                'position' => 6
            ],
            'minimum_balance' => [
                'type' => 'input-number',
                'value' => null,
                'label' => 'Balance minimo',
                'placeholder' => 'balance-minimo',
                'default_value' => '0',
                'position' => 7
            ],
            'membership_percentage' => [
                'type' => 'input-number',
                'value' => null,
                'label' => 'Porcentaje de socio',
                'placeholder' => 'porcentaje-de-socio',
                'position' => 8
            ],
            'create_invoice' => [
                'label' => 'Crear factura (despues de hacer el descuento)',
                'placeholder' => 'create-invoices-after-charge-invoice-',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 9
            ],
            'autopay_invoice' => [
                'label' => 'Auto pagar factura desde la cuenta',
                'placeholder' => 'auto-pay-invoices-from-account-balance',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 10
            ],
            'send_financial_notification' => [
                'label' => 'Enviar notificaciones de finanzas',
                'placeholder' => 'enviar-notificaciones-de-finanzas',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 11
            ],
        ],
    ],
    'ClientBillingAddress' => [
        'FIELDS' => [
            'billing_name' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Nombre de Facturación',
                'placeholder' => 'Nombre de Facturación',
                'position' => 1
            ],
            'billing_street' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Calle de Facturación',
                'placeholder' => 'Calle de Facturación',
                'position' => 2
            ],
            'billing_zip_code' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Código Postal de la facturación',
                'placeholder' => 'Código Postal de la facturación',
                'position' => 3
            ],
            'billing_city' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Ciudad de Facturación',
                'placeholder' => 'Ciudad de Facturación',
                'position' => 4
            ],
        ],
    ],
    'ClientRemindersConfiguration' => [
        'FIELDS' => [
            'activate_reminders' => [
                'label' => 'Activar recordatorios',
                'placeholder' => 'Activar recordatorios',
                'type' => 'input-checkbox',
                'default_value' => true,
                'value' => false,
                'position' => 1
            ],
            'type_of_message' => [
                'label' => 'Tipo de mensaje',
                'placeholder' => 'Seleccione el tipo de mensaje',
                'type' => 'select-component',
                'value' => null,
                'default_value' => 'Mail + SMS',
                'options' => ['Mail' => 'Mail', 'SMS' => 'SMS', 'Mail + SMS' => 'Mail + SMS'],
                'position' => 2
            ],
            'reminder_1_days' => [
                'type' => 'input-number',
                'value' => null,
                'label' => 'Recordatorio #1 día',
                'placeholder' => 'Recordatorio #1 día',
                'default_value' => 5,
                'position' => 3
            ],
            'reminder_2_days' => [
                'type' => 'input-number',
                'value' => null,
                'label' => 'Recordatorio #2 día',
                'placeholder' => 'Recordatorio #2 día',
                'default_value' => 3,
                'position' => 4
            ],
            'reminder_3_days' => [
                'type' => 'input-number',
                'value' => null,
                'label' => 'Recordatorio #3 día',
                'placeholder' => 'Recordatorio #3 día',
                'default_value' => 1,
                'position' => 5
            ],
            'reminder_payment_amount' => [
                'type' => 'input-number',
                'value' => null,
                'label' => 'Cantidad del pago de recordatorio',
                'placeholder' => '0.00',
                'position' => 6
            ],
            'reminder_payment_comment' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Comentario del pago de recordatorio',
                'placeholder' => 'Comentario del pago de recordatorio',
                'position' => 7
            ],
        ]
    ],
    'ClientDebitRectificationAgreement'=> [
        'FIELDS' => [
            'apply_group_of_months' => [
                'label' => 'Porciento descuento por Meses de débito',
                'placeholder' => 'Seleccione meses de débito.',
                'type' => 'select-component',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\SettingDebtPaymentClientCustom',
                    'id' => 'percent_discount',
                    'text' => 'apply_group_of_months'
                ],
                'position' => 1
            ],
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
                'position' => 2
            ],
        ]
    ]
];
