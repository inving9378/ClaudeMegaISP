<?php

return [
    'Crm' => [
        'FIELDS' => [
            'name' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Nombre',
                'placeholder' => 'nombre',
                'position' => 1
            ],
            'father_last_name' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Apellido Paterno',
                'placeholder' => 'Apellido Paterno',
                'position' => 1
            ],
            'mother_last_name' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Apellido Materno',
                'placeholder' => 'Apellido Materno',
                'position' => 1
            ],
            'email' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Correo',
                'placeholder' => 'correo',
                'position' => 2
            ],
            'email_is_required' => [
                'label' => 'Correo es requerido',
                'placeholder' => '',
                'type' => 'input-checkbox',
                'value' => false,
                'default_value' => true,
                'position' => 3
            ],
            'phone' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Telefono',
                'placeholder' => 'telefono',
                'position' => 4
            ],
            'phone2' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Telefono2',
                'placeholder' => 'telefono2',
                'position' => 4
            ],
            'nif_pasaport' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'RFC/CURP',
                'placeholder' => '',
                'position' => 4
            ],
            'partner_id' => [
                'label' => 'Socios',
                'placeholder' => 'Seleccionar el socio',
                'type' => 'select-component',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\Partner',
                    'id' => 'id',
                    'text' => 'name'
                ],
                'position' => 5
            ],
            'location_id' => [
                'label' => 'Ubicación',
                'placeholder' => 'Seleccionar ubicacion',
                'type' => 'select-component',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\Location',
                    'id' => 'id',
                    'text' => 'name'
                ],
                'position' => 6
            ],
            'street' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Calle',
                'placeholder' => 'calle',
                'position' => 13
            ],
            'external_number' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Numero Exterior',
                'placeholder' => 'numero exterior',
                'default_value' => 'Mz',
                'position' => 14
            ],
            'internal_number' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Numero Interior',
                'placeholder' => 'numero interior',
                'default_value' => 'Lt',
                'position' => 15
            ],
            'owner_id' => [
                'label' => 'Vendedor',
                'placeholder' => 'Seleccionar el vendedor',
                'type' => 'select-component',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\User',
                    'id' => 'id',
                    'text' => 'name'
                ],
                'position' => 9,
                'default_value' => 'user_authenticated'
            ],
            'crm_status' => [
                'label' => 'CRM Status',
                'placeholder' => 'Seleccionar el Status',
                'type' => 'select-component',
                'value' => null,
                'options' => [
                    'Nuevo' => "Nuevo",
                    'Contactado' => "Contactado",
                    'Interesado' => "Interesado",
                    'Cotización' => "Cotización",
                    'Ganado' => "Ganado",
                    'Perdido' => "Perdido"
                ],
                'default_value' => 'Nuevo',
                'position' => 10
            ],
            'enable_same_name_or_rfc' => [
                'label' => 'Permitir Usuario Duplicado',
                'placeholder' => '',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 11
            ],
        ],
        'DATATABLE_FIELDS' => [
            'crm_status' => [
                'name' => 'Estado',
                'class' => null
            ],
            'name' => [
                'name' => 'Nombre',
                'class' => null
            ],
            'father_last_name' => [
                'name' => 'Apellido Paterno',
                'class' => null
            ],
            'mother_last_name' => [
                'name' => 'Apellido Materno',
                'class' => null
            ],
            'phone' => [
                'name' => 'Teléfono',
                'class' => null
            ],
            'action' => [
                'name' => 'Acciones',
                'class' => null
            ]
        ],
    ],
    'CrmMainInformation' => [
        'FIELDS' => [
            'ift' => [
                'label' => 'IFT',
                'placeholder' => 'ift',
                'type' => 'input-string',
                'value' => null,
                'position' => 2
            ],
            'name' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Nombre',
                'placeholder' => 'nombre',
                'position' => 3
            ],
            'father_last_name' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Apellido Paterno',
                'placeholder' => 'Apellido Paterno',
                'position' => 4
            ],
            'mother_last_name' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Apellido Materno',
                'placeholder' => 'Apellido Materno',
                'position' => 5
            ],
            'email' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Correo',
                'placeholder' => 'correo',
                'position' => 6
            ],
            'phone' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Telefono',
                'placeholder' => 'telefono',
                'position' => 7
            ],
            'phone2' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Telefono 2',
                'placeholder' => 'telefono 2',
                'position' => 7
            ],
            'nif_pasaport' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'RFC/CURP',
                'placeholder' => '',
                'position' => 8
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
                'position' => 9
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
                'position' => 10
            ],
            'high_date' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Fecha de alta',
                'placeholder' => 'fecha-de-alta',
                'position' => 12
            ],
            'street' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Calle',
                'placeholder' => 'calle',
                'position' => 13
            ],
            'external_number' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Numero Exterior',
                'placeholder' => 'numero exterior',
                'default_value' => 'Mz',
                'position' => 14
            ],
            'internal_number' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Numero Interior',
                'placeholder' => 'numero interior',
                'default_value' => 'Lt',
                'position' => 15
            ],
            'colony_id' => [
                'label' => 'App\Models\CrmMainInformation',
                'placeholder' => 'Seleccionar Colonia',
                'type' => 'select-2-estado-municipio-colonia-component',
                'value' => null,
                'position' => 16,
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
                'label' => 'Codigo Postal',
                'placeholder' => 'codigo-postal',
                'position' => 19
            ],
        ]
    ],
    'CrmLeadInformation' => [
        'FIELDS' => [
            'score' => [
                'type' => 'input-string-information',
                'value' => null,
                'label' => 'Score',
                'placeholder' => 'score',
                'position' => 1
            ],
            'last_contacted' => [
                'type' => 'input-string-information',
                'value' => null,
                'label' => 'Last Contacted',
                'placeholder' => 'last-contacted',
                'position' => 2
            ],
            'instalation_date' => [
                'type' => 'date-time-local',
                'value' => null,
                'label' => 'Dia de instalacion',
                'placeholder' => 'Agregue fecha de instalacion',
                'position' => 2
            ],
            'crm_techical_user_id' => [
                'label' => 'Tecnico',
                'placeholder' => 'Seleccionar el técnico encargado',
                'type' => 'select-component',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\User',
                    'id' => 'id',
                    'text' => 'name'
                ],
                'position' => 2
            ],
            'crm_status' => [
                'label' => 'Estado del CRM',
                'placeholder' => 'Seleccionar el estado',
                'type' => 'select-component',
                'value' => null,
                'options' => [
                    'Nuevo' => "Nuevo",
                    'Contactado' => "Contactado",
                    'Interesado' => "Interesado",
                    'Cotización' => "Cotización",
                    'Instalacion' => "Instalacion",
                    'Perdido' => "Perdido"
                ],
                'position' => 3
            ],
            'owner_id' => [
                'label' => 'Propietario',
                'placeholder' => 'Seleccionar el propietario',
                'type' => 'select-component',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\User',
                    'id' => 'id',
                    'text' => 'name'
                ],
                'position' => 4
            ],
            'source' => [
                'type' => 'input-string',
                'value' => null,
                'label' => 'Comentario',
                'placeholder' => 'comentario',
                'position' => 5
            ],
        ]
    ],
    'DocumentCrm' => [
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
