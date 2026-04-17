<?php

return [
    'Mikrotik' => [
        'FIELDS' => [
            'active' => [
                'label' => 'Habilitar API',
                'placeholder' => '',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 1
            ],
            'login_api' => [
                'label' => 'Usuario (API)',
                'placeholder' => '',
                'type' => 'input-string',
                'value' => null,
                'position' => 2
            ],
            'password_api' => [
                'type' => 'input-password-in-modal',
                'value' => null,
                'label' => 'Contraseña (API)',
                'placeholder' => 'contraseña',
                'position' => 3
            ],
            'port_api' => [
                'label' => 'Puerto (API)',
                'placeholder' => '8730',
                'type' => 'input-string',
                'value' => null,
                'position' => 4
            ],
            'shaper_active' => [
                'label' => 'Habilitar Shaper',
                'placeholder' => '',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 5
            ],
            'shaper' => [
                'label' => 'Shaper',
                'placeholder' => 'Seleccionar Shaper',
                'type' => 'select-component',
                'value' => 'Este enrutador',
                'options' => ['Este enrutador' => 'Este enrutador'],
                'position' => 6
            ],
            'shaping_type' => [
                'label' => 'Tipo de shaping',
                'placeholder' => 'Seleccionar el tipo de shaping',
                'type' => 'select-component',
                'value' => 'Simple queue(Con árbol de cola)',
                'options' => [
                    'Simple queue(Con árbol de cola)' => 'Simple queue(Con árbol de cola)',
                ],
                'position' => 7
            ],

            'rule_wireless_access_list' => [
                'partition' => 'init',
                'label' => 'Lista de accesos a Morosos',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'url_redirect' => [
                        'field' => 'url_redirect',
                        'type' => 'input-string',
                        'value' => null,
                        'label' => 'Url de Redirección',
                        'placeholder' => 'http://ejemplo.com',
                        'position' => 1
                    ],
                    'ip_redirect' => [
                        'field' => 'ip_redirect',
                        'type' => 'input-string',
                        'value' => null,
                        'label' => 'IP de redirección',
                        'placeholder' => '0.0.0.0',
                        'position' => 1
                    ],
                    'ips_with_comma_permited' => [
                        'field' => 'ips_with_comma_permited',
                        'type' => 'input-string',
                        'value' => null,
                        'label' => 'IPs',
                        'placeholder' => 'IPs de sitios permitidos separados por coma,sin espacios',
                        'position' => 1
                    ],
                ]),
                'position' => 9
            ],
            'url_redirect' => [
                'type' => 'depend-field',
            ],
            'ip_redirect' => [
                'type' => 'depend-field',
            ],
            'ips_with_comma_permited' => [
                'type' => 'depend-field',
            ],
            'port_redirect' => [
                'type' => 'depend-field',
            ],
            'rule_address_list_mobility_client' => [
                'label' => 'Movilidad de Clientes de la Address-List',
                'placeholder' => '',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 10
            ],
            'bloking_rules' => [
                'label' => 'Reglas de Bloqueo',
                'placeholder' => '',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 11
            ],
        ]
    ],
    'MikrotikConfig' => [
        'FIELDS' => [
            'meganet_config_ip_address' => [
                'label' => 'IP/Host Meganet',
                'placeholder' => '',
                'type' => 'input-string',
                'value' => null,
                'position' => 1
            ],
            'custom_config_name_parent_router' => [
                'label' => 'Queue nombre Hilo Padre',
                'placeholder' => '',
                'type' => 'input-string',
                'value' => null,
                'position' => 2
            ],
            'custom_config_comment_parent_router' => [
                'label' => 'Queue comentario Hilo Padre',
                'placeholder' => '',
                'type' => 'input-string',
                'value' => null,
                'position' => 3
            ],
            'custom_config_comment_sun_router' => [
                'label' => 'Queue comentario Hilo Hijo',
                'placeholder' => '',
                'type' => 'input-string',
                'value' => null,
                'position' => 4
            ],
            'mikrotik_config_server_pppoe_name' => [
                'label' => 'Nombre del Servidor Ppoe',
                'placeholder' => '',
                'type' => 'input-string',
                'value' => null,
                'position' => 5
            ],
            'mikrotik_config_server_pppoe_interface' => [
                'label' => 'Nombre Interface para Servidor Ppoe',
                'placeholder' => '',
                'type' => 'input-string',
                'value' => null,
                'position' => 6
            ],
            'mikrotik_config_server_pppoe_mtu' => [
                'label' => 'Servidor Ppoe MTU',
                'placeholder' => '',
                'type' => 'input-string',
                'value' => null,
                'position' => 7
            ],
            'mikrotik_config_server_pppoe_mru' => [
                'label' => 'Servidor Ppoe MRU',
                'placeholder' => '',
                'type' => 'input-string',
                'value' => null,
                'position' => 8
            ],
            'mikrotik_config_server_pppoe_profile' => [
                'label' => 'Servidor Ppoe Nombre del Perfil',
                'placeholder' => '',
                'type' => 'input-string',
                'value' => null,
                'position' => 9
            ],
            'mikrotik_config_server_ppp_profile' => [
                'label' => 'Nombre Interface para Servidor Ppoe',
                'placeholder' => '',
                'type' => 'input-string',
                'value' => null,
                'position' => 10
            ],
            'mikrotik_config_server_ppp_local_address' => [
                'label' => 'Direccion local servidor Ppp',
                'placeholder' => '',
                'type' => 'input-string',
                'value' => null,
                'position' => 11
            ],
            'mikrotik_config_server_ppp_remote_address' => [
                'label' => 'Direccion remota servidor Ppp',
                'placeholder' => '',
                'type' => 'input-string',
                'value' => null,
                'position' => 12
            ],
            'mikrotik_config_server_ppp_bridge' => [
                'label' => 'Nombre de la Interfaz puente',
                'placeholder' => '',
                'type' => 'input-string',
                'value' => null,
                'position' => 13
            ],
        ],
    ]
];
