<?php

return [
    'ClientBundleService' => [
        'FIELDS' => [
            'bundle_id' => [
                'partition' => 'init',
                'label' => 'Tarifas',
                'placeholder' => 'Seleccionar las tarifas ...',
                'type' => 'select-component',
                'include' => false,
                'value' => null,
                'search' => [
                    'model' => 'App\Models\Bundle',
                    'id' => 'id',
                    'text' => 'title'
                ],
                'position' => 1,
            ],
            'bundle_description' => [
                'partition' => 'bundle_service_option',
                'type' => 'input-string',
                'value' => null,
                'label' => 'Descripción',
                'placeholder' => '',
                'position' => 2
            ],
            'bundle_price' => [
                'partition' => 'bundle_service_option',
                'type' => 'input-number',
                'value' => null,
                'label' => 'Precio',
                'placeholder' => '',
                'position' => 3
            ],
            'bundle_estado' => [
                'partition' => 'bundle_service_option',
                'label' => 'Estado',
                'placeholder' => 'Seleccionar estado del paquete',
                'type' => 'select-component',
                'value' => null,
                'options' => ['Activado' => 'Activado', 'Desactivado' => 'Desactivado', 'Pendiente' => 'Pendiente'],
                'default_value' => 'Pendiente',
                'position' => 4
            ],
            'bundle_pay_period' => [
                'partition' => 'bundle_service_option',
                'label' => 'Período de Pago',
                'placeholder' => 'Seleccionar periodo de pago',
                'type' => 'select-component',
                'value' => null,
                'options' => ['Periodo 1' => 'Periodo 1', 'Periodo 2' => 'Periodo 2', 'Periodo 3' => 'Periodo 3', 'Periodo 4' => 'Periodo 4', 'Periodo 5' => 'Periodo 5'],
                'default_value' => 'Periodo 1',
                'position' => 5
            ],
            'bundle_discount' => [
                'partition' => 'bundle_service_option',
                'label' => 'Descuento',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'bundle_discount_percent' => [
                        'field' => 'bundle_discount_percent',
                        'label' => 'Porciento de descuento',
                        'placeholder' => 'descuento',
                        'type' => 'input-group-text',
                        'inputGroup' => '%',
                        'value' => null,
                        'position' => 1
                    ],
                    'bundle_start_date_discount' => [
                        'field' => 'bundle_start_date_discount',
                        'type' => 'date-time-local',
                        'value' => null,
                        'label' => 'Fecha de Inicio de Descuento',
                        'placeholder' => '01/01/2021',
                        'position' => 2
                    ],
                    'bundle_end_date_discount' => [
                        'field' => 'bundle_end_date_discount',
                        'type' => 'date-time-local',
                        'value' => null,
                        'label' => 'Fecha de Finalización de Descuento',
                        'placeholder' => '01/01/2021',
                        'position' => 3
                    ],
                    'bundle_discount_message' => [
                        'field' => 'bundle_discount_message',
                        'type' => 'input-string',
                        'value' => null,
                        'label' => 'Mensaje de descuento',
                        'placeholder' => 'entre su mensaje',
                        'position' => 4
                    ],
                ]),
                'position' => 9
            ],
            'bundle_discount_percent' => [
                'include' => false,
            ],
            'bundle_start_date_discount' => [
                'include' => false,
            ],
            'bundle_end_date_discount' => [
                'include' => false,
            ],
            'bundle_discount_message' => [
                'include' => false,
            ],
            'bundle_contract_start_date' => [
                'partition' => 'contract_information',
                'type' => 'date-time-local',
                'value' => null,
                'label' => 'Inicio de Contrato',
                'placeholder' => '01/01/2021',
                'default_value' => 'now',
                'position' => 1
            ],
            'bundle_automatic_renewal' => [
                'partition' => 'contract_information',
                'label' => 'Renovacion Automatica',
                'placeholder' => '',
                'type' => 'input-checkbox',
                'value' => true,
                'position' => 2
            ],

            'plan_internet_router_id' => [
                'partition' => 'internet_service',
                'label' => 'Router',
                'placeholder' => 'Seleccionar el router',
                'type' => 'select-component',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\Router',
                    'id' => 'id',
                    'text' => 'title'
                ],
                'position' => 1
            ],
            'plan_internet_client_name' => [
                'partition' => 'internet_service',
                'type' => 'input-string',
                'value' => null,
                'label' => 'Usuario',
                'placeholder' => 'Nombre del usuario',
                'default_value' => [
                    'request' => '/cliente/get-user-for-client',
                ],
                'disabled' => true,
                'position' => 2,
            ],
            'plan_internet_password' => [
                'partition' => 'internet_service',
                'type' => 'input-password',
                'value' => null,
                'label' => 'Contraseña',
                'placeholder' => '',
                'position' => 3
            ],
            'plan_internet_ipv4_assignment' => [
                'partition' => 'internet_service',
                'label' => 'Método de Asignación de IP',
                'placeholder' => 'Ninguno (enrutador asignará IP)',
                'type' => 'select-component-with-group-inputs',
                'value' => false,
                'options' => ['IP Estatica' => 'IP Estatica', 'Pool IP' => 'Pool IP'],

                'depend' => 'option',
                'inputs_depend' => json_encode([
                    'plan_internet_ipv4' => [
                        'field' => 'plan_internet_ipv4',
                        'label' => 'Dirección IPv4',
                        'placeholder' => 'Selecione dirección Ipv4',
                        'type' => 'select-2-component',
                        'value' => null,
                        'depend' => 'IP Estatica',
                        'options' => null,
                        'search' => [
                            'model' => 'App\Models\NetworkIp',
                            'id' => 'id',
                            'text' => 'ip',
                            'filter' => [
                                [
                                    'field_relation' => 'network',
                                    'field' => 'type_of_use',
                                    'value' => 'Estatico'
                                ],
                                [
                                    'field' => 'used',
                                    'value' => 0
                                ],
                                [
                                    'or_where' => 'used_by',
                                    'value' => 'bundle_id'
                                ]
                            ]
                        ],
                        'position' => 1
                    ],
                    'plan_internet_additional_ipv4' => [
                        'field' => 'plan_internet_additional_ipv4',
                        'label' => 'Red adicional IPv4',
                        'placeholder' => 'Introducir Red',
                        'type' => 'input-string',
                        'value' => null,
                        'depend' => 'IP Estatica',
                        'position' => 2
                    ],
                    'plan_internet_ipv4_pool' => [
                        'field' => 'plan_internet_ipv4_pool',
                        'label' => 'Ipv4 Pools',
                        'placeholder' => 'Selecione Pool de Ipv4',
                        'type' => 'select-component',
                        'value' => null,
                        'depend' => 'Pool IP',
                        'options' => null,
                        'search' => [
                            'model' => 'App\Models\Network',
                            'id' => 'id',
                            'text' => 'network',
                            'filter' => [
                                [
                                    'field' => 'type_of_use',
                                    'value' => 'Pool'
                                ]
                            ]
                        ],
                        'position' => 0
                    ],
                ]),

                'position' => 4
            ],
            'plan_internet_ipv4' => [
                'include' => false,
            ],
            'plan_internet_additional_ipv4' => [
                'include' => false,
            ],
            'plan_internet_ipv4_pool' => [
                'include' => false,
            ],
            'plan_internet_ipv6' => [
                'partition' => 'internet_service',
                'type' => 'input-string',
                'value' => null,
                'label' => 'Ipv6',
                'placeholder' => '',
                'position' => 5
            ],
            'plan_internet_delegated_ipv6' => [
                'partition' => 'internet_service',
                'type' => 'input-string',
                'value' => null,
                'label' => 'Ipv6 Delegada',
                'placeholder' => '',
                'position' => 6
            ],
            'plan_internet_mac' => [
                'partition' => 'other',
                'type' => 'input-string',
                'value' => null,
                'label' => 'Mac(s,)',
                'placeholder' => '',
                'position' => 7
            ],
            'plan_internet_portid' => [
                'partition' => 'other',
                'type' => 'input-string',
                'value' => null,
                'label' => 'Port ID',
                'placeholder' => '',
                'position' => 8
            ],

            'plan_voz_phone' => [
                'partition' => 'voz_service',
                'type' => 'input-string',
                'value' => null,
                'label' => 'Teléfono',
                'placeholder' => 'telefono',
                'position' => 1
            ],
            'plan_voz_password' => [
                'partition' => 'voz_service',
                'type' => 'input-password',
                'value' => null,
                'label' => 'Contraseña',
                'placeholder' => '',
                'position' => 2
            ],
            'plan_voz_voise_device' => [
                'partition' => 'voz_service',
                'label' => 'Dispositivo de Voz',
                'placeholder' => 'Seleccionar dispositivo de voz',
                'type' => 'select-component',
                'value' => null,
                'options' => ['Ninguno' => 'Ninguno', 'VoIP' => 'VoIP'],
                'position' => 3
            ],
            'plan_voz_direction' => [
                'partition' => 'voz_service',
                'label' => 'Dirección',
                'placeholder' => 'Seleccionar dirección',
                'type' => 'select-component',
                'value' => null,
                'options' => ['Salientes' => 'Salientes', 'Entrantes' => 'Entrantes'],
                'position' => 4
            ],

            'plan_custom_hidden' => [
                'partition' => 'custom_service',
                'label' => '-',
                'placeholder' => '-',
                'type' => 'input-hidden',
                'value' => null,
                'position' => 1
            ],
            'plan_custom_service_name' => [
                'partition' => 'custom_service',
                'label' => 'Servicio',
                'type' => 'input-string-information',
                'value' => null,
                'position' => 2
            ],
            'plan_custom_price' => [
                'partition' => 'custom_service',
                'label' => 'Precio',
                'type' => 'input-string-information',
                'value' => null,
                'position' => 3
            ],
            'plan_custom_user' => [
                'partition' => 'custom_service',
                'type' => 'input-string',
                'value' => null,
                'label' => 'Usuario',
                'placeholder' => 'usuario',
                'position' => 4
            ],
            'plan_custom_password' => [
                'partition' => 'custom_service',
                'type' => 'input-string',
                'value' => null,
                'label' => 'Contraseña',
                'placeholder' => 'contraseña',
                'position' => 5
            ]
        ],
        'DATATABLE_FIELDS' => [
            'id' => [
                'name' => 'id',
                'class' => null
            ],
            'estado' => [
                'name' => 'Estado',
                'class' => null
            ],
            'description' => [
                'name' => 'description',
                'class' => null
            ],
            'price' => [
                'name' => 'Tarifa',
                'class' => null
            ],
            'contract_start_date' => [
                'name' => 'Fecha Inicio',
                'class' => null
            ],
            'contract_end_date' => [
                'name' => 'Fecha Finalización',
                'class' => null
            ],
            'action' => [
                'name' => 'Acciones',
                'class' => null
            ]
        ],
    ],
    'ClientInternetService' => [
        'FIELDS' => [
            'internet_id' => [
                'partition' => 'init',
                'label' => 'Tarifas',
                'placeholder' => 'Seleccionar las tarifas ...',
                'type' => 'select-component',
                'include' => false,
                'value' => null,
                'search' => [
                    'model' => 'App\Models\Internet',
                    'id' => 'id',
                    'text' => 'title'
                ],
                'position' => 1,
            ],
            'description' => [
                'partition' => 'init',
                'type' => 'input-string',
                'value' => null,
                'label' => 'Descripción',
                'placeholder' => '',
                'disabled' => true,
                'position' => 2
            ],
            'amount' => [
                'partition' => 'init',
                'type' => 'input-number',
                'value' => null,
                'label' => 'Cantidad',
                'placeholder' => '',
                'default_value' => 1,
                'position' => 3
            ],
            'unity' => [
                'partition' => 'init',
                'type' => 'input-number',
                'value' => null,
                'label' => 'Unidad',
                'placeholder' => '',
                'default_value' => 1,
                'position' => 4
            ],
            'price' => [
                'partition' => 'init',
                'type' => 'input-number',
                'value' => null,
                'label' => 'Precio',
                'placeholder' => '',
                'disabled' => true,
                'position' => 5
            ],
            'pay_period' => [
                'partition' => 'init',
                'label' => 'Período de Pago',
                'placeholder' => 'Seleccionar Período de Pago',
                'type' => 'select-component',
                'value' => null,
                'options' => ['Periodo 1' => 'Periodo 1', 'Periodo 2' => 'Periodo 2', 'Periodo 3' => 'Periodo 3', 'Periodo 4' => 'Periodo 4', 'Periodo 5' => 'Periodo 5'],
                'default_value' => 'Periodo 1',
                'position' => 6
            ],
            'start_date' => [
                'partition' => 'init',
                'type' => 'date-time-local',
                'value' => null,
                'label' => 'Fecha de Inicio',
                'placeholder' => '01/01/2021',
                'default_value' => 'now',
                'position' => 7
            ],
            'finish_date' => [
                'partition' => 'init',
                'type' => 'date-time-local',
                'value' => null,
                'label' => 'Fecha en que termina',
                'placeholder' => '01/01/2021',
                'position' => 7
            ],
            'discount' => [
                'partition' => 'init',
                'label' => 'Descuento',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'discount_percent' => [
                        'field' => 'discount_percent',
                        'label' => 'Porciento de descuento',
                        'placeholder' => 'descuento',
                        'type' => 'input-group-text',
                        'inputGroup' => '%',
                        'value' => null,
                        'position' => 1
                    ],
                    'start_date_discount' => [
                        'field' => 'start_date_discount',
                        'type' => 'date-time-local',
                        'value' => null,
                        'label' => 'Fecha de Inicio de Descuento',
                        'placeholder' => '01/01/2021',
                        'position' => 2
                    ],
                    'end_date_discount' => [
                        'field' => 'end_date_discount',
                        'type' => 'date-time-local',
                        'value' => null,
                        'label' => 'Fecha de Finalización de Descuento',
                        'placeholder' => '01/01/2021',
                        'position' => 3
                    ],
                    'discount_message' => [
                        'field' => 'discount_message',
                        'type' => 'input-string',
                        'value' => null,
                        'label' => 'Mensaje de descuento',
                        'placeholder' => 'entre su mensaje',
                        'position' => 4
                    ],
                ]),
                'position' => 9
            ],
            'discount_percent' => [
                'include' => false,
            ],
            'start_date_discount' => [
                'include' => false,
            ],
            'end_date_discount' => [
                'include' => false,
            ],
            'discount_message' => [
                'include' => false,
            ],

            'estado' => [
                'partition' => 'init',
                'label' => 'Estado',
                'placeholder' => 'Seleccionar el Estado',
                'type' => 'select-component',
                'value' => null,
                'options' => ['Activado' => 'Activado', 'Desactivado' => 'Desactivado', 'Pendiente' => 'Pendiente'],
                'default_value' => 'Pendiente',
                'position' => 10
            ],
            'router_id' => [
                'partition' => 'other',
                'label' => 'Router',
                'placeholder' => 'Seleccionar el router',
                'type' => 'select-component',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\Router',
                    'id' => 'id',
                    'text' => 'title'
                ],
                'position' => 11
            ],
            'client_name' => [
                'partition' => 'other',
                'type' => 'input-string',
                'value' => null,
                'label' => 'Usuario',
                'placeholder' => 'Nombre del usuario',
                'position' => 12,
                'default_value' => [
                    'request' => '/cliente/get-user-for-client',
                ],
                'disabled' => true
            ],
            'password' => [
                'partition' => 'other',
                'type' => 'input-password',
                'value' => null,
                'label' => 'Contraseña',
                'placeholder' => '',
                'position' => 13
            ],

            'ipv4_assignment' => [
                'partition' => 'other',
                'label' => 'Método de Asignación de IP',
                'placeholder' => 'Ninguno (enrutador asignará IP)',
                'type' => 'select-component-with-group-inputs',
                'value' => false,
                'options' => ['IP Estatica' => 'IP Estatica', 'Pool IP' => 'Pool IP'],

                'depend' => 'option',
                'inputs_depend' => json_encode([
                    'ipv4' => [
                        'field' => 'ipv4',
                        'label' => 'Dirección IPv4',
                        'placeholder' => 'Selecione dirección Ipv4',
                        'type' => 'select-2-component',
                        'value' => null,
                        'depend' => 'IP Estatica',
                        'options' => null,
                        'search' => [
                            'model' => 'App\Models\NetworkIp',
                            'id' => 'id',
                            'text' => 'ip',
                            'filter' => [
                                [
                                    'field_relation' => 'network',
                                    'field' => 'type_of_use',
                                    'value' => 'Estatico'
                                ],
                                [
                                    'field' => 'used',
                                    'value' => 0
                                ],
                                [
                                    'or_where' => 'used_by',
                                    'value' => 'client_id'
                                ]
                            ]
                        ],
                        'position' => 1
                    ],
                    'additional_ipv4' => [
                        'field' => 'additional_ipv4',
                        'label' => 'Red adicional IPv4',
                        'placeholder' => 'Introducir Red',
                        'type' => 'input-string',
                        'value' => null,
                        'depend' => 'IP Estatica',
                        'position' => 2
                    ],
                    'ipv4_pool' => [
                        'field' => 'ipv4_pool',
                        'label' => 'Ipv4 Pools',
                        'placeholder' => 'Selecione Pool de Ipv4',
                        'type' => 'select-component',
                        'value' => null,
                        'depend' => 'Pool IP',
                        'options' => null,
                        'search' => [
                            'model' => 'App\Models\Network',
                            'id' => 'id',
                            'text' => 'network',
                            'filter' => [
                                [
                                    'field' => 'type_of_use',
                                    'value' => 'Pool'
                                ]
                            ]
                        ],
                        'position' => 0
                    ],
                ]),

                'position' => 14
            ],
            'ipv4' => [
                'include' => false,
            ],
            'additional_ipv4' => [
                'include' => false,
            ],
            'ipv4_pool' => [
                'include' => false,
            ],

            'ipv6' => [
                'partition' => 'other',
                'type' => 'input-string',
                'value' => null,
                'label' => 'Ipv6',
                'placeholder' => '',
                'position' => 15
            ],
            'delegated_ipv6' => [
                'partition' => 'other',
                'type' => 'input-string',
                'value' => null,
                'label' => 'Ipv6 Delegada',
                'placeholder' => '',
                'position' => 16
            ],
            'mac' => [
                'partition' => 'other',
                'type' => 'input-string',
                'value' => null,
                'label' => 'Mac(s,)',
                'placeholder' => '',
                'position' => 17
            ],
            'portid' => [
                'partition' => 'other',
                'type' => 'input-string',
                'value' => null,
                'label' => 'Port ID',
                'placeholder' => '',
                'position' => 18
            ],


            'payment_type' => [
                'partition' => 'other',
                'label' => 'Tipo de pago',
                'placeholder' => 'Seleccione el tipo de pago..',
                'type' => 'select-component-with-group-inputs',
                'value' => false,
                'options' => [
                    'Pago al crear el servicio' => 'Pago al crear el servicio',
                    'Pago al finalizar el servicio' => 'Pago al finalizar el servicio',
                    'Pago diferido' => 'Pago diferido'
                ],

                'depend' => 'option',
                'inputs_depend' => json_encode([
                    'deferred_payment_in_month' => [
                        'field' => 'deferred_payment_in_month',
                        'label' => 'Meses',
                        'placeholder' => 'Seleccione en cuantos meses',
                        'type' => 'select-component',
                        'value' => null,
                        'depend' => 'Pago diferido',
                        'options' => [
                            '1mes' => "1mes",
                            '3meses' => "3meses",
                            '6meses' => "6meses",
                            '9meses' => "9meses",
                            '12meses' => "12meses",
                        ],
                        'position' => 1
                    ],
                ]),
                'position' => 19
            ],
            'deferred_payment_in_month' => [
                'include' => false,
            ],
            'cost_activation' => [
                'partition' => 'other',
                'type' => 'input-number',
                'value' => null,
                'label' => 'Costo de activacion',
                'placeholder' => '',
                'disabled' => true,
                'position' => 20
            ],
        ],
        'DATATABLE_FIELDS' => [
            'id' => [
                'name' => 'id',
                'class' => null
            ],
            'estado' => [
                'name' => 'Estado',
                'class' => null
            ],
            'internet_id' => [
                'name' => 'Tarifa',
                'class' => null
            ],
            'start_date' => [
                'name' => 'Fecha Inicio',
                'class' => null
            ],
            'finish_date' => [
                'name' => 'Fecha Finalización',
                'class' => null
            ],
            'action' => [
                'name' => 'Acciones',
                'class' => null
            ]
        ],
    ],
    'ClientVozService' => [
        'FIELDS' => [
            'voz_id' => [
                'partition' => 'init',
                'label' => 'Tarifas',
                'placeholder' => 'Seleccionar las tarifas ...',
                'type' => 'select-component',
                'include' => false,
                'value' => null,
                'search' => [
                    'model' => 'App\Models\Voise',
                    'id' => 'id',
                    'text' => 'title'
                ],
                'position' => 1
            ],
            'description' => [
                'partition' => 'init',
                'type' => 'input-string',
                'value' => null,
                'label' => 'Descripción',
                'placeholder' => '',
                'disabled' => true,
                'position' => 2
            ],
            'amount' => [
                'partition' => 'init',
                'type' => 'input-number',
                'value' => null,
                'label' => 'Cantidad',
                'placeholder' => '',
                'default_value' => 1,
                'position' => 3
            ],
            'unity' => [
                'partition' => 'init',
                'type' => 'input-number',
                'value' => null,
                'label' => 'Unidad',
                'placeholder' => '',
                'default_value' => 1,
                'position' => 4
            ],
            'price' => [
                'partition' => 'init',
                'type' => 'input-number',
                'value' => null,
                'label' => 'Precio',
                'placeholder' => '',
                'disabled' => true,
                'position' => 5
            ],
            'pay_period' => [
                'partition' => 'init',
                'label' => 'Período de Pago',
                'placeholder' => 'Seleccionar Periodo de Pago',
                'type' => 'select-component',
                'value' => null,
                'options' => ['Periodo 1' => 'Periodo 1', 'Periodo 2' => 'Periodo 2', 'Periodo 3' => 'Periodo 3', 'Periodo 4' => 'Periodo 4', 'Periodo 5' => 'Periodo 5'],
                'default_value' => 'Periodo 1',
                'position' => 6
            ],
            'finish_date' => [
                'partition' => 'init',
                'type' => 'date-time-local',
                'value' => null,
                'label' => 'Fecha en que termina',
                'placeholder' => '01/01/2021',
                'position' => 8
            ],
            'start_date' => [
                'partition' => 'init',
                'type' => 'date-time-local',
                'value' => null,
                'label' => 'Fecha de Inicio',
                'placeholder' => '01/01/2021',
                'default_value' => 'now',
                'position' => 7
            ],
            'discount' => [
                'partition' => 'init',
                'label' => 'Descuento',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'discount_percent' => [
                        'field' => 'discount_percent',
                        'label' => 'Porciento de descuento',
                        'placeholder' => 'descuento',
                        'type' => 'input-group-text',
                        'inputGroup' => '%',
                        'value' => null,
                        'position' => 1
                    ],
                    'start_date_discount' => [
                        'field' => 'start_date_discount',
                        'type' => 'date-time-local',
                        'value' => null,
                        'label' => 'Fecha de Inicio de Descuento',
                        'placeholder' => '01/01/2021',
                        'position' => 2
                    ],
                    'end_date_discount' => [
                        'field' => 'end_date_discount',
                        'type' => 'date-time-local',
                        'value' => null,
                        'label' => 'Fecha de Finalización de Descuento',
                        'placeholder' => '01/01/2021',
                        'position' => 3
                    ],
                    'discount_message' => [
                        'field' => 'discount_message',
                        'type' => 'input-string',
                        'value' => null,
                        'label' => 'Mensaje de descuento',
                        'placeholder' => 'entre su mensaje',
                        'position' => 4
                    ],
                ]),
                'position' => 9
            ],
            'discount_percent' => [
                'include' => false,
            ],
            'start_date_discount' => [
                'include' => false,
            ],
            'end_date_discount' => [
                'include' => false,
            ],
            'discount_message' => [
                'include' => false,
            ],
            'estado' => [
                'partition' => 'init',
                'label' => 'Estado',
                'placeholder' => 'Seleccionar el Estado',
                'type' => 'select-component',
                'value' => null,
                'options' => ['Activado' => 'Activado', 'Desactivado' => 'Desactivado'],
                'default_value' => 'Activado',
                'position' => 10
            ],
            'phone' => [
                'partition' => 'other',
                'type' => 'input-string',
                'value' => null,
                'label' => 'Teléfono',
                'placeholder' => 'telefono',
                'position' => 11
            ],
            'password' => [
                'partition' => 'other',
                'type' => 'input-password',
                'value' => null,
                'label' => 'Contraseña',
                'placeholder' => '',
                'position' => 12
            ],
            'voise_device' => [
                'partition' => 'other',
                'label' => 'Dispositivo de Voz',
                'placeholder' => 'Seleccionar dispositivo de voz',
                'type' => 'select-component',
                'value' => null,
                'options' => ['Ninguno' => 'Ninguno', 'VoIP' => 'VoIP'],
                'position' => 13
            ],
            'direction' => [
                'partition' => 'other',
                'label' => 'Dirección',
                'placeholder' => 'Seleccionar direccion',
                'type' => 'select-component',
                'value' => null,
                'options' => ['Salientes' => 'Salientes', 'Entrantes' => 'Entrantes'],
                'position' => 14
            ],
        ],
        'DATATABLE_FIELDS' => [
            'id' => [
                'name' => 'id',
                'class' => null
            ],
            'estado' => [
                'name' => 'Estado',
                'class' => null
            ],
            'description' => [
                'name' => 'Estado',
                'class' => null
            ],
            'voz_id' => [
                'name' => 'Tarifa',
                'class' => null
            ],
            'start_date' => [
                'name' => 'Fecha Inicio',
                'class' => null
            ],
            'finish_date' => [
                'name' => 'Fecha Finalización',
                'class' => null
            ],
            'phone' => [
                'name' => 'teléfono',
                'class' => null
            ],
            'direction' => [
                'name' => 'Dirección',
                'class' => null
            ],
            'action' => [
                'name' => 'Acciones',
                'class' => null
            ]
        ],
    ],
    'ClientCustomService' => [
        'FIELDS' => [
            'custom_id' => [
                'partition' => 'init',
                'label' => 'Tarifas',
                'placeholder' => 'Seleccionar las tarifas ...',
                'type' => 'select-component',
                'include' => false,
                'value' => null,
                'search' => [
                    'model' => 'App\Models\Custom',
                    'id' => 'id',
                    'text' => 'title'
                ],
                'position' => 1,
            ],
            'description' => [
                'partition' => 'init',
                'type' => 'input-string',
                'value' => null,
                'label' => 'Descripción',
                'placeholder' => '',
                'disabled' => true,
                'position' => 2
            ],
            'amount' => [
                'partition' => 'init',
                'type' => 'input-number',
                'value' => null,
                'label' => 'Cantidad',
                'placeholder' => '',
                'default_value' => 1,
                'position' => 3
            ],
            'unity' => [
                'partition' => 'init',
                'type' => 'input-number',
                'value' => null,
                'label' => 'Unidad',
                'placeholder' => '',
                'default_value' => 1,
                'position' => 4
            ],
            'price' => [
                'partition' => 'init',
                'type' => 'input-number',
                'value' => null,
                'label' => 'Precio',
                'placeholder' => '',
                'disabled' => true,
                'position' => 5
            ],
            'pay_period' => [
                'partition' => 'init',
                'label' => 'Período de Pago',
                'placeholder' => 'Seleccionar el Período de Pago',
                'type' => 'select-component',
                'value' => null,
                'options' => ['Periodo 1' => 'Periodo 1', 'Periodo 2' => 'Periodo 2', 'Periodo 3' => 'Periodo 3', 'Periodo 4' => 'Periodo 4', 'Periodo 5' => 'Periodo 5'],
                'default_value' => 'Periodo 1',
                'position' => 6
            ],
            'start_date' => [
                'partition' => 'init',
                'type' => 'date-time-local',
                'value' => null,
                'label' => 'Fecha de Inicio',
                'placeholder' => '01/01/2021',
                'default_value' => 'now',
                'position' => 7
            ],
            'discount' => [
                'partition' => 'init',
                'label' => 'Descuento',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'discount_percent' => [
                        'field' => 'discount_percent',
                        'label' => 'Porciento de descuento',
                        'placeholder' => 'descuento',
                        'type' => 'input-group-text',
                        'inputGroup' => '%',
                        'value' => null,
                        'position' => 1
                    ],
                    'start_date_discount' => [
                        'field' => 'start_date_discount',
                        'type' => 'date-time-local',
                        'value' => null,
                        'label' => 'Fecha de Inicio de Descuento',
                        'placeholder' => '01/01/2021',
                        'position' => 2
                    ],
                    'end_date_discount' => [
                        'field' => 'end_date_discount',
                        'type' => 'date-time-local',
                        'value' => null,
                        'label' => 'Fecha de Finalización de Descuento',
                        'placeholder' => '01/01/2021',
                        'position' => 3
                    ],
                    'discount_message' => [
                        'field' => 'discount_message',
                        'type' => 'input-string',
                        'value' => null,
                        'label' => 'Mensaje de descuento',
                        'placeholder' => 'entre su mensaje',
                        'position' => 4
                    ],
                ]),
                'position' => 9
            ],
            'discount_percent' => [
                'include' => false,
            ],
            'start_date_discount' => [
                'include' => false,
            ],
            'end_date_discount' => [
                'include' => false,
            ],
            'discount_message' => [
                'include' => false,
            ],
            'estado' => [
                'partition' => 'init',
                'label' => 'Estado',
                'placeholder' => 'Seleccionar el Estado',
                'type' => 'select-component',
                'value' => null,
                'options' => ['Activado' => 'Activado', 'Desactivado' => 'Desactivado','Pendiente' => 'Pendiente'],
                'default_value' => 'Pendiente',
                'position' => 10
            ],

            'payment_type' => [
                'partition' => 'init',
                'label' => 'Tipo de pago',
                'placeholder' => 'Seleccione el tipo de pago..',
                'type' => 'select-component-with-group-inputs',
                'value' => false,
                'options' => [
                    'Pago recurrente' => 'Pago recurrente',
                    'Pago unico' => 'Pago unico',
                    'Pago diferido' => 'Pago diferido',
                    // garantia no entra dentro de los costos del cliente
                    'Garantia' => 'Garantia'
                ],

                'depend' => 'option',
                'inputs_depend' => json_encode([
                    'deferred_payment_in_month' => [
                        'field' => 'deferred_payment_in_month',
                        'label' => 'Meses',
                        'placeholder' => 'Seleccione en cuantos meses',
                        'type' => 'select-component',
                        'value' => null,
                        'depend' => 'Pago diferido',
                        'options' => [
                            '1mes' => "1mes",
                            '3meses' => "3meses",
                            '6meses' => "6meses",
                            '9meses' => "9meses",
                            '12meses' => "12meses",
                        ],
                        'position' => 1
                    ],
                ]),
                'position' => 11
            ],
            'deferred_payment_in_month' => [
                'include' => false,
            ],
        ],
        'DATATABLE_FIELDS' => [
            'id' => [
                'name' => 'id',
                'class' => null
            ],
            'estado' => [
                'name' => 'Estado',
                'class' => null
            ],
            'custom_id' => [
                'name' => 'Tarifa',
                'class' => null
            ],
            'start_date' => [
                'name' => 'Fecha Inicio',
                'class' => null
            ],
            'action' => [
                'name' => 'Acciones',
                'class' => null
            ]
        ],
    ],
];
