<?php

return [
    'RouterAdd' => [
        'FIELDS' => [
            'title' => [
                'label' => 'Título',
                'placeholder' => 'título',
                'type' => 'input-string',
                'value' => null,
                'position' => 1
            ],
            'type_of_nas' => [
                'label' => 'Tipo de Nas',
                'placeholder' => 'Seleccionar tipo de Nas',
                'type' => 'select-component',
                'value' => 'Mikrotik',
                'options' => ['Mikrotik' => 'Mikrotik', 'Cisco' => 'Cisco', 'Ubiquiti' => 'Ubiquiti'],
                'position' => 2
            ],
            'vendor_model' => [
                'label' => 'Vendedor/Modelo',
                'placeholder' => 'Vendedor/Modelo',
                'type' => 'input-string',
                'value' => null,
                'position' => 3
            ],
            'partners' => [
                'partition' => 'init',
                'label' => 'Socios',
                'placeholder' => 'socios',
                'type' => 'select-component-with-checkbox',
                'value' => [],
                'search' => [
                    'model' => 'App\Models\Partner',
                    'id' => 'id',
                    'text' => 'name'
                ],
                'position' => 4
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
                'position' => 5
            ],
            'physical_address' => [
                'label' => 'Dirección física',
                'placeholder' => 'Dirección física',
                'type' => 'input-string',
                'value' => null,
                'position' => 6
            ],
            'ip_host' => [
                'label' => 'IP/Host',
                'placeholder' => '0.0.0.0',
                'type' => 'input-string',
                'value' => null,
                'position' => 7
            ],
            'nas_ip' => [
                'label' => 'NAS IP',
                'placeholder' => '0.0.0.0',
                'type' => 'input-string',
                'value' => null,
                'position' => 8
            ],
            'authorization_accounting' => [
                'label' => 'Autorización / Contabilidad ',
                'placeholder' => 'Ninguno/Ninguno',
                'type' => 'select-component',
                'value' => 'PPP(Secrets)/API Acounting',
                'options' => [
                    'PPP(Secrets)/API Acounting' => 'PPP(Secrets)/API Acounting',
                    'Hostpot(Users)/API accounting' => 'Hostpot(Users)/API accounting',
                    'Hostopt(Radius)/Radius' => 'Hostopt(Radius)/Radius'
                ],
                'position' => 9
            ],
        ]
    ],
    'Router' => [
        'FIELDS' => [
            'title' => [
                'partition' => 'init',
                'label' => 'Título',
                'placeholder' => 'título',
                'type' => 'input-string',
                'value' => null,
                'position' => 1
            ],
            'type_of_nas' => [
                'partition' => 'init',
                'label' => 'Tipo de Nas',
                'placeholder' => 'Seleccione tipo de Nas',
                'type' => 'select-component',
                'value' => 'Mikrotik',
                'options' => ['Mikrotik' => 'Mikrotik', 'Cisco' => 'Cisco', 'Ubiquiti' => 'Ubiquiti'],
                'position' => 2
            ],
            'vendor_model' => [
                'partition' => 'init',
                'label' => 'Vendedor/Modelo',
                'placeholder' => 'Vendedor/Modelo',
                'type' => 'input-string',
                'value' => null,
                'position' => 3
            ],
            'partners' => [
                'partition' => 'init',
                'label' => 'Socios',
                'placeholder' => 'socios',
                'type' => 'select-component-with-checkbox',
                'value' => [],
                'search' => [
                    'model' => 'App\Models\Partner',
                    'id' => 'id',
                    'text' => 'name'
                ],
                'position' => 4
            ],
            'location_id' => [
                'partition' => 'init',
                'label' => 'Ubicación',
                'placeholder' => 'Seleccione la ubicación',
                'type' => 'select-component',
                'value' => null,
                'search' => [
                    'model' => 'App\Models\Location',
                    'id' => 'id',
                    'text' => 'name'
                ],
                'position' => 5
            ],
            'physical_address' => [
                'partition' => 'init',
                'label' => 'Dirección física',
                'placeholder' => 'Dirección física',
                'type' => 'input-string',
                'value' => null,
                'position' => 6
            ],
            'ip_host' => [
                'partition' => 'init',
                'label' => 'IP/Host',
                'placeholder' => '0.0.0.0',
                'type' => 'input-string',
                'value' => null,
                'position' => 7
            ],
            'authorization_accounting' => [
                'partition' => 'init',
                'label' => 'Autorización / Contabilidad ',
                'placeholder' => 'Ninguno/Ninguno',
                'type' => 'select-component',
                'value' => 'PPP(Secrets)/API Acounting',
                'options' => [
                    'PPP(Secrets)/API Acounting' => 'PPP(Secrets)/API Acounting',
                    'Hostpot(Users)/API accounting' => 'Hostpot(Users)/API accounting',
                    'Hostopt(Radius)/Radius' => 'Hostopt(Radius)/Radius'
                ],
                'position' => 8
            ],
            'secret_radius' => [
                'partition' => 'other',
                'label' => 'Radius  Secreto',
                'placeholder' => '',
                'type' => 'input-string',
                'value' => null,
                'position' => 9
            ],
            'nas_ip' => [
                'partition' => 'other',
                'label' => 'NAS IP',
                'placeholder' => '0.0.0.0',
                'type' => 'input-string',
                'value' => null,
                'position' => 10
            ],
            'pool' => [
                'partition' => 'other',
                'label' => 'Pool',
                'placeholder' => '0.0.0.0/8',
                'type' => 'input-string',
                'value' => null,
                'position' => 11
            ],
        ],
        'DATATABLE_FIELDS' => [
            'title' => [
                'name' => 'Título',
                'class' => null
            ],
            'type_of_nas' => [
                'name' => 'Tipo de NAS',
                'class' => null
            ],
            'vendor_model' => [
                'name' => 'Vendedor/Modelo',
                'class' => null
            ],
            'ip_host' => [
                'name' => 'IP del Host',
                'class' => null
            ],
            'physical_address' => [
                'name' => 'Dirección Físcia',
                'class' => null
            ],
            'status' => [
                'name' => 'Estado',
                'class' => null
            ],
            'action' => [
                'name' => 'Acciones',
                'class' => null
            ]
        ]
    ],
];
