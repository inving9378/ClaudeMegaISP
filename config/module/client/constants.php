<?php

return [
    'Client' => [
        'FIELDS' => [
            'user' => [
                'type' => 'input-group-generate-user',
                'value' => null,
                'label' => 'Usuario',
                'placeholder' => 'usuario',
                'position' => 1
            ],
            'password' => [
                'type' => 'input-password',
                'value' => null,
                'label' => 'Contraseña',
                'placeholder' => 'Contraseña',
                'position' => 2
            ],
            'type_of_billing_id' => [
                'label' => 'Tipo de facturación',
                'placeholder' => 'Seleccionar Tipos de Facturación',
                'type' => 'select-component',
                'value' => null,
                'default_value' => 1,
                'search' => [
                    'model' => 'App\Models\TypeBilling',
                    'id' => 'id',
                    'text' => 'type'
                ],
                'position' => 3
            ],
            'ift' => [
                'label' => 'Ift',
                'placeholder' => 'Seleccionar Ift',
                'type' => 'select-component',
                'value' => null,
                'default_value' => 1,
                'search' => [
                    'model' => 'App\Models\Ift',
                    'id' => 'id',
                    'text' => 'name'
                ],
                'position' => 4
            ],
            'name' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Nombre',
                'placeholder' => 'Nombre',
                'position' => 5
            ],
            'father_last_name' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Apellido Paterno',
                'placeholder' => 'Apellido Parterno',
                'position' => 6
            ],
            'mother_last_name' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Apellido Materno',
                'placeholder' => 'Apellido Materno',
                'position' => 7
            ],
            'email' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Correo',
                'placeholder' => 'correo',
                'position' => 8
            ],
            'phone' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Teléfono',
                'placeholder' => 'Teléfono',
                'position' => 9
            ],
            'category' => [
                'label' => 'Categoría',
                'placeholder' => 'Seleccionar la Categoría',
                'type' => 'select-component',
                'value' => null,
                'options' => ['Particular' => 'Particular', 'Empresa' => 'Empresa'],
                'position' => 10
            ],
            'activation_date' => [
                'type' => 'date-time-local',
                'value' => null,
                'label' => 'Fecha de Activación',
                'placeholder' => '',
                'disabled' => false,
                'default_value' => 'now',
                'position' => 11
            ],
            'nif_pasaport' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'NIF / Pasaporte',
                'placeholder' => '',
                'position' => 12
            ],
            'partner_id' => [
                'label' => 'Socios',
                'placeholder' => 'Seleccionar el Socio',
                'type' => 'select-component',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\Partner',
                    'id' => 'id',
                    'text' => 'name'
                ],
                'position' => 13
            ],
            'location_id' => [
                'label' => 'Ubicación',
                'placeholder' => 'Seleccionar ubicación',
                'type' => 'select-component',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\Location',
                    'id' => 'id',
                    'text' => 'name'
                ],
                'position' => 14
            ],
            'discharge_date' => [
                'type' => 'date-time-local',
                'value' => null,
                'label' => 'Fecha de alta',
                'placeholder' => '',
                'disabled' => true,
                'default_value' => 'now',
                'position' => 15,
            ],
            'street' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Calle',
                'placeholder' => 'Calle',
                'position' => 16
            ],
            'external_number' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Número exterior',
                'placeholder' => 'numero exterior',
                'default_value' => 'Mz',
                'position' => 17
            ],
            'internal_number' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Número interior',
                'placeholder' => 'numero interior',
                'default_value' => 'Lt',
                'position' => 18
            ],
            'colony_id' => [
                'label' => 'Colonia',
                'placeholder' => 'Seleccionar Colonia',
                'type' => 'select-2-estado-municipio-colonia-component',
                'value' => null,
                'position' => 21,
            ],
            'state_id' => [
                'include' => false,
            ],
            'municipality_id' => [
                'include' => false,
            ],
            'zip' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Código Zip',
                'placeholder' => 'Código Zip',
                'position' => 22
            ],
            'geo_data' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Datos geográficos',
                'placeholder' => 'Datos geográficos',
                'position' => 23
            ],
            'modem_sn' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'S/N (SERIE MODEM)',
                'placeholder' => '',
                'position' => 24
            ],
            'phone2' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Teléfono 2',
                'placeholder' => 'Teléfono',
                'position' => 25
            ],
            'gpon_ont' => [
                'label' => 'GPON ONT',
                'placeholder' => '',
                'inputGroup' => 'Mirar / Fijar',
                'type' => 'input-group-text',
                'value' => null,
                'position' => 26
            ],
            'power_dbm' => [
                'label' => 'Potencia en dbm',
                'placeholder' => 'Potencia en dbm',
                'type' => 'input-number',
                'value' => null,
                'step' => '.01',
                'position' => 27
            ],
            'original_password' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Contraseña Original',
                'placeholder' => 'Contraseña Original',
                'position' => 28
            ],
            'box_nomenclator' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Nomenclatura de Caja',
                'placeholder' => 'Nomenclatura de Caja',
                'position' => 29
            ],
            'user_films' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Usuario Peliculas',
                'placeholder' => 'Usuario',
                'position' => 30
            ],
            'password_film' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Contraseña Peliculas',
                'placeholder' => 'Contraseña',
                'position' => 31
            ],
            'rfc' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'RFC',
                'placeholder' => 'RFC',
                'position' => 32
            ],
            'reinstatement' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Reinstalación',
                'placeholder' => 'Reinstalación',
                'position' => 34
            ],
            'social_id' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Social ID',
                'placeholder' => 'Social ID',
                'position' => 35
            ],
            'coverage_notes' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Coverage Notes',
                'placeholder' => 'coverage-notes',
                'position' => 36
            ],
            'comment' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Comentario',
                'placeholder' => 'comentario',
                'position' => 37
            ],
            'installation_on_time' => [
                'label' => 'El tecnico instalo en tiempo',
                'placeholder' => 'Seleccionar si fue Instalado',
                'type' => 'select-component',
                'value' => null,
                'options' => [1 => 'Si', 0 => 'No'],
                'position' => 38
            ],
            'amount_technician_and_why' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Cual fue el monto que cobro el tecnico y porque',
                'placeholder' => 'explique',
                'position' => 39
            ],
            'doubt_signed_contract' => [
                'label' => 'Tiene dudas acerca del contrato que esta firmando',
                'placeholder' => 'Seleccionar si Tiene Dudas',
                'type' => 'select-component',
                'value' => null,
                'options' => [1 => 'Si', 0 => 'No'],
                'position' => 40
            ],
            'technician_attencion' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'El tecnico le atendio con amabilidad y respeto si, no y porque',
                'placeholder' => 'El tecnico le atendio con amabilidad y respeto si, no y porque',
                'position' => 41
            ],
        ],

        'DATATABLE_FIELDS' => [
            'id' => [
                'name' => 'ID',
                'class' => null
            ],
            'user' => [
                'name' => 'Servicio Usuario',
                'class' => null
            ],
            'password' => [
                'name' => 'Contraseña',
                'class' => null
            ],
            'name' => [
                'name' => 'Nombre',
                'class' => null
            ],
            'estado' => [
                'name' => 'Estado',
                'class' => null
            ],
            'amount' => [
                'name' => 'Saldo de la cuenta',
                'class' => null
            ],
            'nif_pasaport' => [
                'name' => 'RFC/CURP',
                'class' => null
            ],
            'father_last_name' => [
                'name' => 'Apellido_Paterno',
                'class' => null
            ],
            'mother_last_name' => [
                'name' => 'Apellido_Materno',
                'class' => null
            ],
            'phone' => [
                'name' => 'Teléfono',
                'class' => null
            ],
            'phone2' => [
                'name' => 'Teléfono 2',
                'class' => null
            ],
            'type_of_billing_id' => [
                'name' => 'Tipo de facturación',
                'class' => null
            ],
            'email' => [
                'name' => 'Correo',
                'class' => null
            ],
            'street' => [
                'name' => 'Calle',
                'class' => null
            ],
            'zip' => [
                'name' => 'Código Zip',
                'class' => null
            ],
            'external_number' => [
                'name' => 'Número Exterior',
                'class' => null
            ],
            'internal_number' => [
                'name' => 'Número Interior',
                'class' => null
            ],
            'category' => [
                'name' => 'Categoría',
                'class' => null
            ],
            'modem_sn' => [
                'name' => 'Modem Serie',
                'class' => null
            ],
            'gpon_ont' => [
                'name' => 'GPON ONT',
                'class' => null
            ],
            'power_dbm' => [
                'name' => 'Potencia dBm',
                'class' => null
            ],
            'olt_power_dbm' => [
                'name' => 'Potencia OLT',
                'class' => null
            ],
            'original_password' => [
                'name' => 'Contraseña Original',
                'class' => null
            ],
            'vendor' => [
                'name' => 'Vendedor',
                'class' => null
            ],
            'box_nomenclator' => [
                'name' => 'Nomenclatura Caja',
                'class' => null
            ],
            'user_film' => [
                'name' => 'Usuario Peliculas',
                'class' => null
            ],
            'password_film' => [
                'name' => 'Contraseña Peliculas',
                'class' => null
            ],
            'password_wifi' => [
                'name' => 'Contraseña Wifi',
                'class' => null
            ],
            'reinstatement' => [
                'name' => 'Reinstalacion',
                'class' => null
            ],
            'social_id' => [
                'name' => 'Social ID',
                'class' => null
            ],
            'comment' => [
                'name' => 'Comentario',
                'class' => null
            ],
            'installation_on_time' => [
                'name' => 'El técnico instalo en tiempo y forma',
                'class' => null
            ],
            'amount_technician_and_why' => [
                'name' => 'Cual fue el monto que cobro el técnico y por qué?',
                'class' => null
            ],
            'doubt_signed_contract' => [
                'name' => 'Tiene dudas acerca del contrato que esta firmando',
                'class' => null
            ],
            'technician_attencion' => [
                'name' => 'El técnico le atendio con amabilidad y respeto si, no y por qué?',
                'class' => null
            ],
            'last_time_online' => [
                'name' => 'Última vez online',
                'class' => null
            ],
            'billing_activated' => [
                'name' => 'Facturación habilitada',
                'class' => null
            ],
            'type_billing_id' => [
                'name' => 'Tipo de facturación',
                'class' => null
            ],
            'period' => [
                'name' => 'Periodo',
                'class' => null
            ],
            'billing_date' => [
                'name' => 'Día de facturación',
                'class' => null
            ],
            'billing_expiration' => [
                'name' => 'Vencimiento de la facturación',
                'class' => null
            ],
            'grace_period' => [
                'name' => 'Periodo de desactivación',
                'class' => null
            ],
            'autopay_invoice' => [
                'name' => 'Pago automático de facturas del saldo de la cuenta',
                'class' => null
            ],
            'send_financial_notification' => [
                'name' => 'Enviar notificaciones de finanzas',
                'class' => null
            ],

            'partner_id' => [
                'name' => 'Socios',
                'class' => null
            ],
            'state_id' => [
                'name' => 'Estado',
                'class' => null
            ],
            'municipality_id' => [
                'name' => 'Municipio',
                'class' => null
            ],
            'colony_id' => [
                'name' => 'Colonia',
                'class' => null
            ],
            'internet_fees' => [
                'name' => 'Tarifa de Internet',
                'class' => null
            ],
            'voz_fees' => [
                'name' => 'Tarifa de Voz',
                'class' => null
            ],
            'custom_fees' => [
                'name' => 'Tarifa de Personalizados',
                'class' => null
            ],
            'bundle_fees' => [
                'name' => 'Tarifa de Paquetes',
                'class' => null
            ],
            'price' => [
                'name' => 'Precio',
                'class' => null
            ],
            'voz_price' => [
                'name' => 'Precio del servicio de voz',
                'class' => null
            ],
            'custom_price' => [
                'name' => 'Precio del servicio de custom',
                'class' => null
            ],
            'recurrent_price' => [
                'name' => 'Precio del servicio de recurrent',
                'class' => null
            ],
            'ip_ranges' => [
                'name' => 'Rangos de Ip',
                'class' => null
            ],
            'location_id' => [
                'name' => 'Ubicacion',
                'class' => null
            ],
            'router' => [
                'name' => 'Router',
                'class' => null
            ],
            'redes_adicionales' => [
                'name' => 'Redes Adicionales',
                'class' => null
            ],
            'ipv6' => [
                'name' => 'IPv6',
                'class' => null
            ],
            'ipv6_delegada' => [
                'name' => 'IPv6 Delegada',
                'class' => null
            ],
            'mac' => [
                'name' => 'MAC',
                'class' => null
            ],
            'payment_method_id' => [
                'name' => 'Método de Pago',
                'class' => null
            ],
            'activate_reminders' => [
                'name' => 'Activar recordatorios',
                'class' => null
            ],

            'type_of_message' => [
                'name' => 'Tipo de mensaje',
                'class' => null
            ],
            'reminder_1_days' => [
                'name' => 'Recordatorio #1 día',
                'class' => null
            ],
            'reminder_2_days' => [
                'name' => 'Recordatorio #2 día',
                'class' => null
            ],
            'reminder_3_days' => [
                'name' => 'Recordatorio #3 día',
                'class' => null
            ],
            'reminder_payment_3' => [
                'name' => 'Recordatorio de pago # 3',
                'class' => null
            ],
            'reminder_payment_amount' => [
                'name' => 'Cantidad del pago de recordatorio',
                'class' => null
            ],
            'reminder_payment_comment' => [
                'name' => 'Comentario del pago de recordatorio',
                'class' => null
            ],
            'billing_name' => [
                'name' => 'Nombre de Facturación',
                'class' => null
            ],
            'billing_street' => [
                'name' => 'Calle de Facturación',
                'class' => null
            ],
            'billing_zip_code' => [
                'name' => 'Código Postal de la facturación',
                'class' => null
            ],
            'billing_city' => [
                'name' => 'Ciudad de Facturación',
                'class' => null
            ],
            'internet_services_start_date' => [
                'name' => 'Fecha de inicio de los servicios de Internet',
                'class' => null
            ],
            'internet_services_end_date' => [
                'name' => 'Fecha de finalización de los servicios de Internet',
                'class' => null
            ],
            'voice_services_start_date' => [
                'name' => 'Fecha de comienzo de los servicios de Voz',
                'class' => null
            ],
            'voice_services_end_date' => [
                'name' => 'Fecha de Terminación de los servicios de Voz',
                'class' => null
            ],
            'custom_services_start_date' => [
                'name' => 'Fecha de inicio de los servicios cusmtom',
                'class' => null
            ],
            'custom_services_end_date' => [
                'name' => 'Fecha de finalización de los servicios cusmtom',
                'class' => null
            ],
            'recurring_services_start_date' => [
                'name' => 'Fecha de inicio de los servicios recurrentes',
                'class' => null
            ],
            'recurring_services_end_date' => [
                'name' => 'Fecha de finalización de los servicios recurrentes',
                'class' => null
            ],
            'custom_days_left' => [
                'name' => 'Días de duración de prepago',
                'class' => null
            ],
            'created_at' => [
                'name' => 'Última actualización',
                'class' => null
            ],
            'updated_at' => [
                'name' => 'Creado',
                'class' => null
            ],

            'action' => [
                'name' => 'Acciones',
                'class' => null
            ]
        ],
    ],
    'ClientMainInformation' => [
        'FIELDS' => [
            'user' => [
                'type' => 'input-group-generate-user',
                'value' => null,
                'label' => 'Usuario',
                'placeholder' => 'usuario',
                'position' => 1
            ],
            'password' => [
                'type' => 'input-password-in-modal',
                'value' => null,
                'label' => 'Contraseña',
                'placeholder' => 'contraseña',
                'position' => 2
            ],
            'estado' => [
                'label' => 'Estado',
                'placeholder' => 'Seleccionar el Estado',
                'type' => 'select-component',
                'value' => null,
                'options' => [
                    'Nuevo' => "Nuevo(Todavía no conectado)",
                    'Activo' => "Activado",
                    'Inactivo' => "Inactivo(No puede utilizar los servicios)",
                    'Bloqueado' => "Bloqueado"
                ],
                'position' => 3
            ],
            'type_of_billing_id' => [
                'label' => 'Tipo de facturacion',
                'placeholder' => 'Seleccionar Tipo de Facturación',
                'type' => 'select-component',
                'value' => null,
                'default_value' => 1,
                'search' => [
                    'model' => 'App\Models\TypeBilling',
                    'id' => 'id',
                    'text' => 'type'
                ],
                'position' => 4
            ],
            'ift' => [
                'label' => 'Ift',
                'placeholder' => 'Seleccionar Ift',
                'type' => 'select-component',
                'value' => null,
                'default_value' => 1,
                'search' => [
                    'model' => 'App\Models\Ift',
                    'id' => 'id',
                    'text' => 'name'
                ],
                'position' => 5
            ],
            'name' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Nombre',
                'placeholder' => 'nombre',
                'position' => 6
            ],
            'father_last_name' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Apellido Paterno',
                'placeholder' => 'Apellido Parterno',
                'position' => 7
            ],
            'mother_last_name' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Apellido Materno',
                'placeholder' => 'Apellido Materno',
                'position' => 8
            ],
            'email' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Correo',
                'placeholder' => 'correo',
                'position' => 9
            ],
            'phone' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Telefono',
                'placeholder' => 'telefono',
                'position' => 10
            ],
            'nif_pasaport' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'RFC/CURP',
                'placeholder' => '',
                'position' => 11
            ],
            'phone2' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Teléfono 2',
                'placeholder' => 'Teléfono',
                'position' => 11
            ],
            'partner_id' => [
                'label' => 'Socios',
                'placeholder' => 'Seleccionar socios',
                'type' => 'select-component',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\Partner',
                    'id' => 'id',
                    'text' => 'name'
                ],
                'position' => 12
            ],
            'location_id' => [
                'label' => 'Ubicación',
                'placeholder' => 'Seleccionar la Ubicación',
                'type' => 'select-component',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\Location',
                    'id' => 'id',
                    'text' => 'name'
                ],
                'position' => 13
            ],
            'street' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Calle',
                'placeholder' => 'Calle',
                'position' => 14
            ],
            'external_number' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Número externo',
                'placeholder' => 'numero exterior',
                'default_value' => 'Mz',
                'position' => 15
            ],
            'internal_number' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Número interno',
                'placeholder' => 'numero interior',
                'default_value' => 'Lt',
                'position' => 16
            ],
            'colony_id' => [
                'label' => 'App\Models\ClientMainInformation',
                'placeholder' => 'Seleccionar Colonia',
                'type' => 'select-2-estado-municipio-colonia-component',
                'value' => null,
                'position' => 17,
            ],
            'state_id' => [
                'include' => false,
            ],
            'municipality_id' => [
                'include' => false,
            ],
            'zip' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Código Zip',
                'placeholder' => 'Código Zip',
                'position' => 22
            ],
            'geo_data' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Datos geográficos',
                'placeholder' => 'Datos geográficos',
                'position' => 23
            ],
            'discharge_date' => [
                'type' => 'date-time-local',
                'value' => null,
                'label' => 'Fecha de alta',
                'placeholder' => '',
                'disabled' => true,
                'default_value' => 'now',
                'position' => 24
            ],
            'activation_date' => [
                'type' => 'date-time-local',
                'value' => null,
                'label' => 'Fecha de Activación',
                'placeholder' => '',
                'disabled' => true,
                'default_value' => 'now',
                'position' => 25
            ]
        ]
    ],
    'ClientAdditionalInformation' => [
        'FIELDS' => [
            'connection_type' => [
                'label' => 'Tipo de Conexión',
                'placeholder' => 'Seleccionar tipo',
                'type' => 'select-component',
                'value' => null,
                'options' => ['olt' => 'olt', 'wifi' => 'wifi'],
                'position' => 1
            ],
            'category' => [
                'label' => 'Categoría',
                'placeholder' => 'Seleccionar Categoría',
                'type' => 'select-component',
                'value' => null,
                'options' => ['Particular' => 'Particular', 'Empresa' => 'Empresa'],
                'position' => 2
            ],
            'modem_sn' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'S/N (SERIE MODEM)',
                'placeholder' => '',
                'position' => 9
            ],
            'gpon_ont' => [
                'label' => 'GPON ONT',
                'placeholder' => '',
                'inputGroup' => 'Mirar / Fijar',
                'type' => 'input-group-text',
                'value' => null,
                'position' => 11
            ],
            'power_dbm' => [
                'label' => 'Potencia en dbm',
                'placeholder' => 'Potencia en dbm',
                'type' => 'input-number',
                'value' => null,
                'step' => '.01',
                'position' => 12
            ],
            'original_password' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Contraseña Original',
                'placeholder' => 'Contraseña Original',
                'position' => 13
            ],
            'box_nomenclator' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Nomenclatura de Caja',
                'placeholder' => 'Nomenclatura de Caja',
                'position' => 14
            ],
            'user_film' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Usuario Peliculas',
                'placeholder' => 'Usuario',
                'position' => 15
            ],
            'password_film' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Contraseña Peliculas',
                'placeholder' => 'Contraseña',
                'position' => 16
            ],
            'password_wifi' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Contraseña de Wifi',
                'placeholder' => 'Contraseña',
                'position' => 17
            ],
            'reinstatement' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Reinstalación',
                'placeholder' => 'Reinstalación',
                'position' => 19
            ],
            'social_id' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Social ID',
                'placeholder' => 'Social ID',
                'position' => 20
            ],
            'comment' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Comentario',
                'placeholder' => 'comentario',
                'position' => 22
            ],
            'installation_on_time' => [
                'label' => 'El técnico instalo en tiempo',
                'placeholder' => 'Elija una opción',
                'type' => 'select-component',
                'value' => null,
                'options' => [1 => 'Si', 0 => 'No'],
                'position' => 23
            ],
            'amount_technician_and_why' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Cual fue el monto que cobro el técnico y porque',
                'placeholder' => 'explique',
                'position' => 24
            ],
            'doubt_signed_contract' => [
                'label' => 'Tiene dudas acerca del contrato que esta firmando',
                'placeholder' => 'Elija una opción',
                'type' => 'select-component',
                'value' => null,
                'options' => [1 => 'Si', 0 => 'No'],
                'position' => 25
            ],
            'technician_attencion' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'El técnico le atendio con amabilidad y respeto si, no y porque',
                'placeholder' => 'El técnico le atendió con amabilidad y respeto si, no y porque',
                'position' => 26
            ],
        ]
    ],
    'DocumentClient' => [
        'FIELDS' => [
            'title' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Titulo',
                'placeholder' => 'titulo',
                'position' => 0
            ],
            'description' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Descripcion',
                'placeholder' => 'descripcion',
                'position' => 0
            ],
            'show' => [
                'label' => 'Visible',
                'placeholder' => 'visible',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 0
            ],
            'file' => [
                'type' => 'input-file',
                'value' => null,
                'label' => '',
                'placeholder' => '',
                'position' => 0
            ],
        ],
        'DATATABLE_FIELDS' => [
            'title' => [
                'name' => 'Título',
                'class' => null
            ],
            'description' => [
                'name' => 'Descripción',
                'class' => null
            ],
            'show' => [
                'name' => 'Mostrar al Usuario',
                'class' => null
            ],
            'action' => [
                'name' => 'Acciones',
                'class' => null
            ]
        ],
    ],
];
