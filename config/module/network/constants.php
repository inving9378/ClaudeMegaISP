<?php

return [
    'Network' => [
        'FIELDS' => [
            'network' => [
                'label' => 'Red',
                'placeholder' => '0.0.0.0',
                'type' => 'input-string',
                'value' => null,
                'position' => 1
            ],
            'bm' => [
                'label' => 'BM',
                'placeholder' => 'Seleccionar máscara de Red',
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
            'allow_usage_network' => [
                'label' => 'Permitir el Uso del network y el Broadcast',
                'placeholder' => '',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 3
            ],
            'title' => [
                'label' => 'Título',
                'placeholder' => '',
                'type' => 'input-string',
                'value' => null,
                'position' => 4
            ],
            'comment' => [
                'label' => 'Comentario',
                'placeholder' => '',
                'type' => 'input-string',
                'value' => null,
                'position' => 5
            ],
            'location_id' => [
                'label' => 'Ubicación',
                'placeholder' => 'Seleccionar la ubicación',
                'type' => 'select-component',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\Location',
                    'id' => 'id',
                    'text' => 'name'
                ],
                'position' => 6
            ],
            'network_category' => [
                'label' => 'Categoría de Red',
                'placeholder' => 'Seleccionar la categoría de red',
                'type' => 'select-component',
                'value' => null,
                'options' => [
                    'Dev' => 'Dev',
                    'Coorporativa' => 'Coorporativa',
                    'Test' => 'Test',
                    'Producción' => 'Producción',
                ],
                'position' => 7
            ],
            'network_type' => [
                'label' => 'Tipo de Red',
                'placeholder' => 'Seleccionar tipo de red',
                'type' => 'select-component',
                'value' => null,
                'options' => [
                    'RootNet' => 'RootNet',
                    'EndNet' => 'EndNet',
                ],
                'position' => 8
            ],
            'type_of_use' => [
                'label' => 'Tipo de uso',
                'placeholder' => 'Seleccionar tipo de uso',
                'type' => 'select-component',
                'value' => null,
                'options' => [
                    'Estatico' => 'Estatico',
                    'Pool' => 'Pool',
                ],
                'position' => 9
            ],

        ],
        'DATATABLE_FIELDS' => [
            'network' => [
                'name' => 'Red',
                'class' => null
            ],
            'bm' => [
                'name' => 'BM',
                'class' => null
            ],
            'rootnet' => [
                'name' => 'RootNet',
                'class' => null
            ],
            'used' => [
                'name' => 'Usado',
                'class' => null
            ],
            'title' => [
                'name' => 'Título',
                'class' => null
            ],
            'location_id' => [
                'name' => 'Ubicación',
                'class' => null
            ],
            'network_type' => [
                'name' => 'Tipo de Red',
                'class' => null
            ],
            'network_category' => [
                'name' => 'Categoría de Red',
                'class' => null
            ],
            'action' => [
                'name' => 'Acciones',
                'class' => null
            ]
        ]
    ],
    'NetworkEdit' => [
        'FIELDS' => [
            'title' => [
                'label' => 'Título',
                'placeholder' => '',
                'type' => 'input-string',
                'value' => null,
                'position' => 1
            ],
            'network' => [
                'label' => 'Red',
                'placeholder' => '',
                'type' => 'text',
                'value' => null,
                'position' => 2
            ],
            'bm' => [
                'label' => 'BM',
                'placeholder' => '',
                'type' => 'text',
                'value' => null,
                'position' => 3
            ],
            'allow_usage_network' => [
                'label' => 'Permitir el Uso del network y el Broadcast',
                'placeholder' => '',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 4
            ],
            'comment' => [
                'label' => 'Comentario',
                'placeholder' => '',
                'type' => 'input-string',
                'value' => null,
                'position' => 5
            ],
            'location_id' => [
                'label' => 'Ubicación',
                'placeholder' => 'Seleccionar la ubicación',
                'type' => 'select-component',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\Location',
                    'id' => 'id',
                    'text' => 'name'
                ],
                'position' => 6
            ],
            'network_category' => [
                'label' => 'Categoría de Red',
                'placeholder' => 'Seleccionar la categoría de red',
                'type' => 'select-component',
                'value' => null,
                'options' => [
                    'Dev' => 'Dev',
                    'Coorporativa' => 'Coorporativa',
                    'Test' => 'Test',
                    'Producción' => 'Producción',
                ],
                'position' => 7
            ],
            'network_type' => [
                'label' => 'Categoría de Red',
                'placeholder' => '',
                'type' => 'text',
                'value' => null,
                'position' => 8
            ],
            'type_of_use' => [
                'label' => 'Tipo de Uso',
                'placeholder' => 'Seleccionar tipo de uso',
                'type' => 'select-component',
                'value' => null,
                'options' => [
                    'Estatico' => 'Estatico',
                    'Pool' => 'Pool',
                ],
                'position' => 9
            ],

        ],
    ],
];
