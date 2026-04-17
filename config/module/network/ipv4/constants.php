<?php

return [
    'NetworkIp' => [
        'FIELDS' => [
            'ip' => [
                'label' => 'IP',
                'placeholder' => '0.0.0.0',
                'type' => 'input-string',
                'value' => null,
                'position' => 1
            ],
            'used' => [
                'label' => 'Usado',
                'placeholder' => '',
                'type' => 'input-string',
                'value' => null,
                'position' => 2
            ],
            'used_by' => [
                'label' => 'Usado Por',
                'placeholder' => '',
                'type' => 'input-string',
                'value' => null,
                'position' => 3
            ],
            'title' => [
                'label' => 'Título',
                'placeholder' => '',
                'type' => 'input-string',
                'value' => null,
                'position' => 4
            ],
            'hostname' => [
                'label' => 'Título',
                'placeholder' => '',
                'type' => 'input-string',
                'value' => null,
                'position' => 5
            ],
            'location_id' => [
                'label' => 'Ubicación',
                'placeholder' => '',
                'type' => 'input-string',
                'value' => null,
                'position' => 6
            ],
            'host_category' => [
                'label' => 'Categoría de Host',
                'placeholder' => '',
                'type' => 'input-string',
                'value' => null,
                'position' => 7
            ],
            'comment' => [
                'label' => 'Categoría de Host',
                'placeholder' => '',
                'type' => 'input-string',
                'value' => null,
                'position' => 8
            ],
            'client_id' => [
                'label' => 'Cliente',
                'placeholder' => '',
                'type' => 'input-string',
                'value' => null,
                'position' => 8
            ],
        ],
        'DATATABLE_FIELDS' => [
            'id' => [
                'name' => 'ID',
                'class' => null
            ],
            'ip' => [
                'name' => 'IP',
                'class' => null
            ],
            'used' => [
                'name' => 'Utilizado',
                'class' => null
            ],
            'client_name' => [
                'name' => 'Utilizado por',
                'class' => null
            ],
            'title' => [
                'name' => 'Título',
                'class' => null
            ],
            'hostname' => [
                'name' => 'HostName',
                'class' => null
            ],
            'location_id' => [
                'name' => 'Ubicación',
                'class' => null
            ],
            'host_category' => [
                'name' => 'Categoría Host',
                'class' => null
            ],
            'ping' => [
                'name' => 'Ping',
                'class' => null
            ],
            'action' => [
                'name' => 'Acciones',
                'class' => null
            ]
        ],
    ],
    'Ipv4Calculator' => [
        'FIELDS' => [
            'network_calculator' => [
                'label' => 'Red',
                'placeholder' => '0.0.0.0',
                'type' => 'input-string',
                'value' => null,
                'position' => 1
            ],
            'bm_calculator' => [
                'label' => 'BM',
                'placeholder' => 'Mascara de Red',
                'type' => 'select-component',
                'value' => null,
                'options' => [
                    '8' => '8 (255.0.0.0 - 16777214 hosts, 16777216 IP)',
                    '9' => '9 (255.128.0.0 - 8388606 hosts, 8388608 IP)',
                    '10' => '10 (255.192.0.0 - 4194302 hosts, 4194304 IP)',
                    '11' => '11 (255.224.0.0 - 2097150 hosts, 2097152 IP)',
                    '12' => '12 (255.240.0.0 - 1048574 hosts, 1048576 IP)',
                    '13' => '13 (255.248.0.0 - 524286 hosts, 524288 IP)',
                    '14' => '14 (255.252.0.0 - 262142 hosts, 262144 IP)',
                    '15' => '15 (255.254.0.0 - 131070 hosts, 131072 IP)',

                    '16' => '16 (255.255.0.0 - 65534 hosts, 65536 IP)',
                    '17' => '17 (255.255.128.0 - 32766 hosts, 32768 IP)',
                    '18' => '18 (255.255.192.0 - 16382 hosts, 16384 IP)',
                    '19' => '19 (255.255.224.0 - 8190 hosts, 8192 IP)',
                    '20' => '20 (255.255.240.0 - 4094 hosts, 4096 IP)',
                    '21' => '21 (255.255.248.0 - 2046 hosts, 2048 IP)',
                    '22' => '22 (255.255.252.0 - 1022 hosts, 1024 IP)',
                    '23' => '23 (255.255.254.0 - 510 hosts, 512 IP)',

                    '24' => '24 (255.255.255.0 - 254 hosts, 256 IP)',
                    '25' => '25 (255.255.255.128 - 126 hosts, 128 IP)',
                    '26' => '26 (255.255.255.192 - 62 hosts, 64 IP)',
                    '27' => '27 (255.255.255.224 - 30 hosts, 32 IP)',
                    '28' => '28 (255.255.255.240 - 14 hosts, 16 IP)',
                    '29' => '29 (255.255.255.248 - 6 hosts, 8 IP)',
                    '30' => '30 (255.255.255.252 - 2 hosts, 4 IP)',
                    '31' => '31 (255.255.255.254 - 0 hosts, 2 IP)',
                    '32' => '32 (255.255.255.255 - 0 hosts, 1 IP)'
                ],
                'position' => 2
            ],
        ]
    ],
];
