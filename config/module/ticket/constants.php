<?php

return [
    'Ticket' => [
        'FIELDS' => [
             'customer_lead' => [
                'label' => 'Cliente',
                'placeholder' => 'Seleccionar cliente potencial',
                'type' => 'select-2-component',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\ClientMainInformation',
                    'id' => 'client_id',
                    'text' => 'client_name_with_fathers_names'
                ],
                'position' => 1,
            ],
            'hidden' => [
                'label' => 'Escondido',
                'placeholder' => 'Escondido',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 2

            ],
            'assigned_to' => [
                'label' => 'Asignado a:',
                'placeholder' => 'Seleccionar trabajador',
                'type' => 'select-component',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\User',
                    'id' => 'id',
                    'text' => 'name'
                ],
                'position' => 3,
            ],
            'topic' => [
                'label' => 'Tema',
                'placeholder' => 'Tema',
                'type' => 'input-string',
                'value' => null,
                'position' => 4
            ],
            'priority' => [
                'label' => 'Prioridad',
                'placeholder' => 'Seleccionar Prioridad',
                'type' => 'select-component',
                'value' => '3',
                'options' => ['1' => "Urgente", '2' => "Alta", '3' => "Normal", '4' => "Baja"],
                'position' => 5
            ],
            'group' => [
                'label' => 'Grupo',
                'placeholder' => 'Seleccione el grupo',
                'type' => 'select-component',
                'value' => 'Cualquier',
                'options' => ['Cualquier' => "Cualquier", 'IT' => "IT", 'Finanzas' => "Finanzas", 'Ventas' => "Ventas"],
                'position' => 7
            ],
            'type' => [
                'label' => 'Tipo',
                'placeholder' => 'Seleccione el tipo',
                'type' => 'select-component',
                'value' => 'Pregunta',
                'options' => ['Pregunta' => "Pregunta", 'Incidente' => "Incidente", 'Problema' => "Problema", 'Solicitud de funcion' => "Solicitud de funcion", 'Cliente potencial' => "Cliente potencial"],
                'position' => 8
            ],
            'message' => [
                'label' => 'Mensaje',
                'placeholder' => 'Texto',
                'type' => 'input-text-area',
                'value' => null,
                'position' => 9
            ],
            'date_time' => [
                'type' => 'date-time-local',
                'value' => null,
                'label' => 'Fecha y Hora',
                'placeholder' => '',
                'default_value' => 'now',
                'position' => 10,
            ],
            'phone' => [
                'label' => 'Teléfono',
                'placeholder' => '000-000-0000',
                'type' => 'input-string',
                'value' => null,
                'position' => 11
            ],
            'phone2' => [
                'label' => 'Teléfono 2',
                'placeholder' => '000-000-0000',
                'type' => 'input-string',
                'value' => null,
                'position' => 12
            ],
            'colony_id' => [
                'label' => 'Colonia',
                'placeholder' => 'Seleccionar Colonia',
                'type' => 'select-2-component',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\Colony',
                    'id' => 'id',
                    'text' => 'name'
                ],
                'position' => 13,
            ],
            'attachments' => [
                'label' => 'Adjuntar',
                'placeholder' => 'Adjuntar',
                'type' => 'input-multiple-file',
                'value' => null,
                'position' => 14
            ]
        ],
        'DATATABLE_FIELDS' => [
            'id' => [
                'name' => 'ID',
                'class' => null
            ],
            'topic' => [
                'name' => 'Tema',
                'class' => null
            ],
            'customer_lead' => [
                'name' => 'Customer/Lead',
                'class' => null
            ],
            'priority' => [
                'name' => 'Prioridad',
                'class' => null
            ],
            'estado' => [
                'name' => 'Estado',
                'class' => null
            ],
            'group' => [
                'name' => 'Grupo',
                'class' => null
            ],
            'type' => [
                'name' => 'Tipo',
                'class' => null
            ],
            'assigned_to' => [
                'name' => 'Asignado a',
                'class' => null
            ],
            'date_time' => [
                'name' => 'Fecha y Hora',
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
    'TicketDetails' => [
        'FIELDS' => [
            'hidden' => [
                'label' => 'Ocultar Cliente',
                'placeholder' => 'Escondido',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 1

            ],
            'customer_lead' => [
                'label' => 'Cliente potencial',
                'placeholder' => 'Seleccionar cliente potencial',
                'type' => 'select-component',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\ClientMainInformation',
                    'id' => 'client_id',
                    'text' => 'name'
                ],
                'position' => 2,
            ],
            'assigned_to' => [
                'label' => 'Asignado a:',
                'placeholder' => 'Seleccionar trabajador',
                'type' => 'select-component',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\User',
                    'id' => 'id',
                    'text' => 'name'
                ],
                'position' => 3,
            ],
            'topic' => [
                'label' => 'Tema',
                'placeholder' => 'Tema',
                'type' => 'input-string',
                'value' => null,
                'position' => 4
            ],
            'priority' => [
                'label' => 'Prioridad',
                'placeholder' => 'Seleccionar Prioridad',
                'type' => 'select-component',
                'value' => 'Baja',
                'default_value' => '3',
                'options' => ['1' => "Urgente", '2' => "Alta", '3' => "Normal", '4' => "Baja"],
                'position' => 5
            ],
            'estado' => [
                'label' => 'Estado',
                'placeholder' => 'Seleccionar Estado',
                'type' => 'select-component',
                'value' => 'Baja',
                'options' => ['Nuevo' => "Nuevo", 'Trabajo en curso' => "Trabajo en curso", 'Resuelto' => "Resuelto", 'Esperando al cliente' => "Esperando al cliente", 'Esperando al agente' => "Esperando al agente", 'Cerrado' => "Cerrado", 'Reciclado' => "Reciclado"],
                'position' => 6
            ],
            'group' => [
                'label' => 'Grupo',
                'placeholder' => 'Seleccione el grupo',
                'type' => 'select-component',
                'value' => 'Cualquier',
                'options' => ['Cualquier' => "Cualquier", 'IT' => "IT", 'Finanzas' => "Finanzas", 'Ventas' => "Ventas"],
                'position' => 7
            ],
            'type' => [
                'label' => 'Tipo',
                'placeholder' => 'Seleccione el tipo',
                'type' => 'select-component',
                'value' => 'Pregunta',
                'options' => ['Pregunta' => "Pregunta", 'Incidente' => "Incidente", 'Problema' => "Problema", 'Solicitud de funcion' => "Solicitud de funcion", 'Cliente potencial' => "Cliente potencial"],
                'position' => 8
            ],
            'date_time' => [
                'type' => 'date-time-local',
                'value' => null,
                'label' => 'Fecha y Hora',
                'placeholder' => '01/01/2021',
                'position' => 9
            ],
            'phone' => [
                'label' => 'Teléfono',
                'placeholder' => '000-000-0000',
                'type' => 'input-string',
                'value' => null,
                'position' => 10
            ],
            'phone2' => [
                'label' => 'Teléfono 2',
                'placeholder' => '000-000-0000',
                'type' => 'input-string',
                'value' => null,
                'position' => 11
            ],
            'colony_id' => [
                'label' => 'Colonia',
                'placeholder' => 'Seleccionar Colonia',
                'type' => 'select-component',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\Colony',
                    'id' => 'id',
                    'text' => 'name'
                ],
                'position' => 12
            ],
        ],

    ],
    'TicketThread' => [
        'FIELDS' => [
            'message' => [
                'label' => 'Mensaje',
                'placeholder' => 'Texto',
                'type' => 'input-text-area',
                'value' => null,
                'position' => 1
            ],
            'attachments' => [
                'label' => 'Adjuntar',
                'placeholder' => 'Dirección',
                'type' => 'input-multiple-file',
                'value' => null,
                'position' => 2
            ],
        ],

    ],
];
