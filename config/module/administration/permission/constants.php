<?php

return [
    'Permission' => [
        'FIELDS' => [
            'dashboard_system_status' => [
                'partition' => 'dashboard',
                'label' => 'Ver Estado del Sistema',
                'placeholder' => '',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 1
            ],
            'dashboard_clientes' => [
                'partition' => 'dashboard',
                'label' => 'Clientes',
                'placeholder' => '',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 2
            ],
            'dashboard_payroll' => [
                'partition' => 'dashboard',
                'label' => 'Finanza',
                'placeholder' => '',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 3
            ],
            'dashboard_enrutador' => [
                'partition' => 'dashboard',
                'label' => 'Enrutadores',
                'placeholder' => '',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 4
            ],

            //planes
            'plan_internet' => [
                'partition' => 'plan',
                'label' => 'Internet',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'plan_view_internet' => [
                        'field' => 'plan_view_internet',
                        'label' => 'Ver Listado de Planes de Internet',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                    'plan_add_internet' => [
                        'field' => 'plan_add_internet',
                        'label' => 'Agregar Plan de Internet',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 2
                    ],
                    'plan_edit_internet' => [
                        'field' => 'plan_edit_internet',
                        'label' => 'Editar Plan de Internet',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 3
                    ],
                    'plan_delete_internet' => [
                        'field' => 'plan_delete_internet',
                        'label' => 'Eliminar Plan de Internet',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 4
                    ],
                ]),
                'position' => 5
            ],
            'plan_view_internet' => [
                'include' => false,
            ],
            'plan_add_internet' => [
                'include' => false,
            ],
            'plan_edit_internet' => [
                'include' => false,
            ],
            'plan_delete_internet' => [
                'include' => false,
            ],

            'plan_voz' => [
                'partition' => 'plan',
                'label' => 'Voz',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'plan_view_voz' => [
                        'field' => 'plan_view_voz',
                        'label' => 'Ver Listado de Planes de Voz',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                    'plan_add_voz' => [
                        'field' => 'plan_add_voz',
                        'label' => 'Agregar Plan de Voz',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 2
                    ],
                    'plan_edit_voz' => [
                        'field' => 'plan_edit_voz',
                        'label' => 'Editar Plan de Voz',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 3
                    ],
                    'plan_delete_voz' => [
                        'field' => 'plan_delete_voz',
                        'label' => 'Eliminar Plan de Voz',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 4
                    ],
                ]),
                'position' => 6
            ],
            'plan_view_voz' => [
                'include' => false,
            ],
            'plan_add_voz' => [
                'include' => false,
            ],
            'plan_edit_voz' => [
                'include' => false,
            ],
            'plan_delete_voz' => [
                'include' => false,
            ],

            'plan_custom' => [
                'partition' => 'plan',
                'label' => 'Custom',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'plan_view_custom' => [
                        'field' => 'plan_view_custom',
                        'label' => 'Ver Listado de Planes Customs',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                    'plan_add_custom' => [
                        'field' => 'plan_add_custom',
                        'label' => 'Agregar Plan Custom',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 2
                    ],
                    'plan_edit_custom' => [
                        'field' => 'plan_edit_custom',
                        'label' => 'Editar Plan Custom',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 3
                    ],
                    'plan_delete_custom' => [
                        'field' => 'plan_delete_custom',
                        'label' => 'Eliminar Plan Custom',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 4
                    ],
                ]),
                'position' => 6
            ],
            'plan_view_custom' => [
                'include' => false,
            ],
            'plan_add_custom' => [
                'include' => false,
            ],
            'plan_edit_custom' => [
                'include' => false,
            ],
            'plan_delete_custom' => [
                'include' => false,
            ],

            'plan_paquetes' => [
                'partition' => 'plan',
                'label' => 'Paquete',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'plan_view_paquetes' => [
                        'field' => 'plan_view_paquetes',
                        'label' => 'Ver Listado de Planes de Paquetes',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                    'plan_add_paquetes' => [
                        'field' => 'plan_add_paquetes',
                        'label' => 'Agregar Plan de Paquete',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 2
                    ],
                    'plan_edit_paquetes' => [
                        'field' => 'plan_edit_paquetes',
                        'label' => 'Editar Plan de Paquete',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 3
                    ],
                    'plan_delete_paquetes' => [
                        'field' => 'plan_delete_paquetes',
                        'label' => 'Eliminar Plan de Paquete',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 4
                    ],
                ]),
                'position' => 6
            ],
            'plan_view_paquetes' => [
                'include' => false,
            ],
            'plan_add_paquetes' => [
                'include' => false,
            ],
            'plan_edit_paquetes' => [
                'include' => false,
            ],
            'plan_delete_paquetes' => [
                'include' => false,
            ],

            //Crm
            'crm_dashboard' => [
                'partition' => 'crm',
                'label' => 'Ver Dashboard del crm',
                'placeholder' => '',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 1
            ],
            'crm_crm' => [
                'partition' => 'crm',
                'label' => 'Crm',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'crm_view_crm' => [
                        'field' => 'crm_view_crm',
                        'label' => 'Ver Listado de Crm',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                    'crm_add_crm' => [
                        'field' => 'crm_add_crm',
                        'label' => 'Agregar Crm',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 2
                    ],
                    'crm_edit_crm' => [
                        'field' => 'crm_edit_crm',
                        'label' => 'Editar Crm',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 3
                    ],
                    'crm_delete_crm' => [
                        'field' => 'crm_delete_crm',
                        'label' => 'Eliminar Crm',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 6
                    ],
                ]),
                'position' => 6
            ],
            'crm_view_crm' => [
                'include' => false,
            ],
            'crm_add_crm' => [
                'include' => false,
            ],
            'crm_edit_crm' => [
                'include' => false,
            ],
            'crm_delete_crm' => [
                'include' => false,
            ],

            'crm_information' => [
                'partition' => 'crm',
                'label' => 'Informacion del Crm',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'crm_information_view_tab_crm' => [
                        'field' => 'crm_information_view_tab_crm',
                        'label' => 'Ver Pestaña de Informacion del Crm',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                    'crm_information_geolocation_crm' => [
                        'field' => 'crm_information_geolocation_crm',
                        'label' => 'Ver Geo Localizacion del Crm',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                ]),
                'position' => 7
            ],
            'crm_information_view_tab_crm' => [
                'include' => false,
            ],
            'crm_information_geolocation_crm' => [
                'include' => false,
            ],

            'crm_document' => [
                'partition' => 'crm',
                'label' => 'Documentos del Crm',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'crm_document_view_tab_crm' => [
                        'field' => 'crm_document_view_tab_crm',
                        'label' => 'Ver Pestaña de Documentos del Crm',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                    'crm_document_view_crm' => [
                        'field' => 'crm_document_view_crm',
                        'label' => 'Ver Listado de Documentos del Crm',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                    'crm_document_add_crm' => [
                        'field' => 'crm_document_add_crm',
                        'label' => 'Agregar Documento al Crm',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 2
                    ],
                    'crm_document_edit_crm' => [
                        'field' => 'crm_document_edit_crm',
                        'label' => 'Editar Documento Subido al Crm',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 3
                    ],
                    'crm_document_delete_crm' => [
                        'field' => 'crm_document_delete_crm',
                        'label' => 'Eliminar Documento Subido al Crm',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 6
                    ],
                ]),
                'position' => 8
            ],
            'crm_document_view_tab_crm' => [
                'include' => false,
            ],
            'crm_document_view_crm' => [
                'include' => false,
            ],
            'crm_document_add_crm' => [
                'include' => false,
            ],
            'crm_document_edit_crm' => [
                'include' => false,
            ],
            'crm_document_delete_crm' => [
                'include' => false,
            ],


            //Client
            'client_dashboard' => [
                'partition' => 'client',
                'label' => 'Ver Dashboard del Cliente',
                'placeholder' => '',
                'type' => 'input-checkbox',
                'value' => false,
                'position' => 1
            ],
            'client_client' => [
                'partition' => 'client',
                'label' => 'Cliente',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'client_view_client' => [
                        'field' => 'client_view_client',
                        'label' => 'Ver Listado de Clientes',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                    'client_add_client' => [
                        'field' => 'client_add_client',
                        'label' => 'Agregar Cliente',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 2
                    ],
                    'client_edit_client' => [
                        'field' => 'client_edit_client',
                        'label' => 'Editar Cliente',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 3
                    ],
                    'client_delete_client' => [
                        'field' => 'client_delete_client',
                        'label' => 'Eliminar Cliente',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 4
                    ],
                ]),
                'position' => 2
            ],
            'client_view_client' => [
                'include' => false,
            ],
            'client_add_client' => [
                'include' => false,
            ],
            'client_edit_client' => [
                'include' => false,
            ],
            'client_delete_client' => [
                'include' => false,
            ],

            'client_information' => [
                'partition' => 'client',
                'label' => 'Informacion del Cliente',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'client_information_view_tab_client' => [
                        'field' => 'client_information_view_tab_client',
                        'label' => 'Ver Pestaña de Informacion del Cliente',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                    'client_information_geolocation_client' => [
                        'field' => 'client_information_geolocation_client',
                        'label' => 'Ver Geo Localizacion del Cliente',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                ]),
                'position' => 3
            ],
            'client_information_view_tab_client' => [
                'include' => false,
            ],
            'client_information_geolocation_client' => [
                'include' => false,
            ],

            'client_service' => [
                'partition' => 'client',
                'label' => 'Servicio del Cliente',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'client_service_view_tab_client' => [
                        'field' => 'client_service_view_tab_client',
                        'label' => 'Ver Pestaña de Servicio del Cliente',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ]
                ]),
                'position' => 4
            ],
            'client_service_view_tab_client' => [
                'include' => false,
            ],

            'client_service_internet' => [
                'partition' => 'client',
                'label' => 'Servicio de Internet del Cliente',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'client_service_internet_view_client' => [
                        'field' => 'client_service_internet_view_client',
                        'label' => 'Ver Listado de Servicio de Internet del Cliente',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                    'client_service_internet_add_client' => [
                        'field' => 'client_service_internet_add_client',
                        'label' => 'Agregar Servicio de Internet al Cliente',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 2
                    ],
                    'client_service_internet_edit_client' => [
                        'field' => 'client_service_internet_edit_client',
                        'label' => 'Editar Servicio de Internet del Cliente',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 3
                    ],
                    'client_service_internet_delete_client' => [
                        'field' => 'client_service_internet_delete_client',
                        'label' => 'Eliminar Servicio de Internet del Crm',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 6
                    ],
                ]),
                'position' => 5
            ],
            'client_service_internet_view_client' => [
                'include' => false,
            ],
            'client_service_internet_add_client' => [
                'include' => false,
            ],
            'client_service_internet_edit_client' => [
                'include' => false,
            ],
            'client_service_internet_delete_client' => [
                'include' => false,
            ],
            'client_service_voz' => [
                'partition' => 'client',
                'label' => 'Servicio de Voz del Cliente',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'client_service_voz_view_client' => [
                        'field' => 'client_service_voz_view_client',
                        'label' => 'Ver Listado de Servicio de Voz del Cliente',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                    'client_service_voz_add_client' => [
                        'field' => 'client_service_voz_add_client',
                        'label' => 'Agregar Servicio de Voz al Cliente',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 2
                    ],
                    'client_service_voz_edit_client' => [
                        'field' => 'client_service_voz_edit_client',
                        'label' => 'Editar Servicio de Voz del Cliente',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 3
                    ],
                    'client_service_voz_delete_client' => [
                        'field' => 'client_service_voz_delete_client',
                        'label' => 'Eliminar Servicio de Voz del Crm',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 7
                    ],
                ]),
                'position' => 5
            ],
            'client_service_voz_view_client' => [
                'include' => false,
            ],
            'client_service_voz_add_client' => [
                'include' => false,
            ],
            'client_service_voz_edit_client' => [
                'include' => false,
            ],
            'client_service_voz_delete_client' => [
                'include' => false,
            ],
            'client_service_bundle' => [
                'partition' => 'client',
                'label' => 'Servicio de Paquetes del Cliente',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'client_service_bundle_view_client' => [
                        'field' => 'client_service_bundle_view_client',
                        'label' => 'Ver Listado de Servicio de Paquete del Cliente',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                    'client_service_bundle_add_client' => [
                        'field' => 'client_service_bundle_add_client',
                        'label' => 'Agregar Servicio de Paquete al Cliente',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 2
                    ],
                    'client_service_bundle_edit_client' => [
                        'field' => 'client_service_bundle_edit_client',
                        'label' => 'Editar Servicio de Paquete del Cliente',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 3
                    ],
                    'client_service_bundle_delete_client' => [
                        'field' => 'client_service_bundle_delete_client',
                        'label' => 'Eliminar Servicio de Paquete del Crm',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 6
                    ],
                ]),
                'position' => 5
            ],
            'client_service_bundle_view_client' => [
                'include' => false,
            ],
            'client_service_bundle_add_client' => [
                'include' => false,
            ],
            'client_service_bundle_edit_client' => [
                'include' => false,
            ],
            'client_service_bundle_delete_client' => [
                'include' => false,
            ],

            // TODO falta permiso del tab y crud de payroll

            'client_payroll' => [
                'partition' => 'client',
                'label' => 'Facturacion del Cliente',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'client_payroll_view_tab_client' => [
                        'field' => 'client_payroll_view_tab_client',
                        'label' => 'Ver Pestaña de Facturacion del Cliente',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ]
                ]),
                'position' => 9
            ],
            'client_payroll_view_tab_client' => [
                'include' => false,
            ],

            'client_payroll_payment' => [
                'partition' => 'client',
                'label' => 'Pago del Cliente',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'client_payroll_payment_view_client' => [
                        'field' => 'client_payroll_payment_view_client',
                        'label' => 'Ver Listado de Pagos del Cliente',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                    'client_payroll_payment_add_client' => [
                        'field' => 'client_payroll_payment_add_client',
                        'label' => 'Agregar Pago al Cliente',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 2
                    ],
                    'client_payroll_payment_edit_client' => [
                        'field' => 'client_payroll_payment_edit_client',
                        'label' => 'Editar Pago del Cliente',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 3
                    ],
                    'client_payroll_payment_delete_client' => [
                        'field' => 'client_payroll_payment_delete_client',
                        'label' => 'Eliminar Pago del Cliente',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 6
                    ],
                ]),
                'position' => 5
            ],
            'client_payroll_payment_view_client' => [
                'include' => false,
            ],
            'client_payroll_payment_add_client' => [
                'include' => false,
            ],
            'client_payroll_payment_edit_client' => [
                'include' => false,
            ],
            'client_payroll_payment_delete_client' => [
                'include' => false,
            ],

            // Tickets
            'ticket_dashoboard' => [
                'partition' => 'ticket',
                'label' => 'Ticket',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'ticket_view_dashboard' => [
                        'field' => 'ticket_view_dashboard',
                        'label' => 'Ver Dashboard',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                ]),
                'position' => 2
            ],
            'ticket_view_dashboard' => [
                'include' => false,
            ],

            'ticket_abierto' => [
                'partition' => 'ticket',
                'label' => 'Tickets nuevos/abiertos',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'ticket_view_abierto' => [
                        'field' => 'ticket_view_abierto',
                        'label' => 'Ver Listado de Tickets abiertos',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                    'ticket_add_abierto' => [
                        'field' => 'ticket_add_abierto',
                        'label' => 'Agregar Ticket',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 2
                    ],
                    'ticket_edit_abierto' => [
                        'field' => 'ticket_edit_abierto',
                        'label' => 'Editar Ticket',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 3
                    ],
                    'ticket_delete_abierto' => [
                        'field' => 'ticket_delete_abierto',
                        'label' => 'Eliminar Ticket',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 4
                    ],
                ]),
                'position' => 6
            ],
            'ticket_view_abierto' => [
                'include' => false,
            ],
            'ticket_add_abierto' => [
                'include' => false,
            ],
            'ticket_edit_abierto' => [
                'include' => false,
            ],
            'ticket_delete_abierto' => [
                'include' => false,
            ],

            'ticket_cerrado' => [
                'partition' => 'ticket',
                'label' => 'Tickets cerrados',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'ticket_view_cerrado' => [
                        'field' => 'ticket_view_cerrado',
                        'label' => 'Ver Listado de Tickets Cerrados',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                    'ticket_add_cerrado' => [
                        'field' => 'ticket_add_cerrado',
                        'label' => 'Agregar Ticket',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 2
                    ],
                    'ticket_edit_cerrado' => [
                        'field' => 'ticket_edit_cerrado',
                        'label' => 'Editar Ticket',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 3
                    ],
                    'ticket_delete_cerrado' => [
                        'field' => 'ticket_delete_cerrado',
                        'label' => 'Eliminar Ticket',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 4
                    ],
                ]),
                'position' => 6
            ],
            'ticket_view_cerrado' => [
                'include' => false,
            ],
            'ticket_add_cerrado' => [
                'include' => false,
            ],
            'ticket_edit_cerrado' => [
                'include' => false,
            ],
            'ticket_delete_cerrado' => [
                'include' => false,
            ],

            'ticket_reciclados' => [
                'partition' => 'ticket',
                'label' => 'Tickets reciclados',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'ticket_view_reciclado' => [
                        'field' => 'ticket_view_reciclado',
                        'label' => 'Ver Listado de Tickets Reciclados',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                    'ticket_add_reciclado' => [
                        'field' => 'ticket_add_reciclado',
                        'label' => 'Agregar Ticket',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 2
                    ],
                    'ticket_edit_reciclado' => [
                        'field' => 'ticket_edit_reciclado',
                        'label' => 'Editar Ticket',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 3
                    ],
                    'ticket_delete_reciclado' => [
                        'field' => 'ticket_delete_reciclado',
                        'label' => 'Eliminar Ticket',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 4
                    ],
                ]),
                'position' => 6
            ],
            'ticket_view_reciclado' => [
                'include' => false,
            ],
            'ticket_add_reciclado' => [
                'include' => false,
            ],
            'ticket_edit_reciclado' => [
                'include' => false,
            ],
            'ticket_delete_reciclado' => [
                'include' => false,
            ],

            //Mapas
            'maps_maps' => [
                'partition' => 'map',
                'label' => 'Mapas',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'maps_view_maps' => [
                        'field' => 'maps_view_maps',
                        'label' => 'Ver Lista de Mapas',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                ]),
                'position' => 2
            ],
            'maps_view_maps' => [
                'include' => false,
            ],

            // Finanzas
            'finanzas_transacciones' => [
                'partition' => 'finance',
                'label' => 'Transacciones',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'finanzas_view_transacciones' => [
                        'field' => 'finanzas_view_transacciones',
                        'label' => 'Ver Listado de Transacciones',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                ]),
                'position' => 2
            ],
            'finanzas_view_transacciones' => [
                'include' => false,
            ],

            'finanzas_facturas' => [
                'partition' => 'finance',
                'label' => 'Facturas',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'finanzas_view_facturas' => [
                        'field' => 'finanzas_view_facturas',
                        'label' => 'Ver Listado de Facturas',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                ]),
                'position' => 2
            ],
            'finanzas_view_facturas' => [
                'include' => false,
            ],

            'finanzas_pagos' => [
                'partition' => 'finance',
                'label' => 'Pagos',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'finanzas_view_pagos' => [
                        'field' => 'finanzas_view_pagos',
                        'label' => 'Ver Listado de Pagos',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                ]),
                'position' => 2
            ],
            'finanzas_view_pagos' => [
                'include' => false,
            ],

            //Gestión de red
            'ipv4_ipv4' => [
                'partition' => 'router',
                'label' => 'Redes Ipv4',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'ipv4_view_ipv4' => [
                        'field' => 'ipv4_view_ipv4',
                        'label' => 'Ver Listado de Redes',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                    'ipv4_add_ipv4' => [
                        'field' => 'ipv4_add_ipv4',
                        'label' => 'Agregar red',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 2
                    ],
                    'ipv4_edit_ipv4' => [
                        'field' => 'ipv4_edit_ipv4',
                        'label' => 'Editar red',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 3
                    ],
                    'ipv4_delete_ipv4' => [
                        'field' => 'ipv4_delete_ipv4',
                        'label' => 'Eliminar red',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 4
                    ],
                ]),
                'position' => 5
            ],
            'ipv4_view_ipv4' => [
                'include' => false,
            ],
            'ipv4_add_ipv4' => [
                'include' => false,
            ],
            'ipv4_edit_ipv4' => [
                'include' => false,
            ],
            'ipv4_delete_ipv4' => [
                'include' => false,
            ],

            'router_router' => [
                'partition' => 'router',
                'label' => 'Enrutadores',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'router_view_router' => [
                        'field' => 'router_view_router',
                        'label' => 'Ver Listado de Routers',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                    'router_add_router' => [
                        'field' => 'router_add_router',
                        'label' => 'Agregar router',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 2
                    ],
                    'router_edit_router' => [
                        'field' => 'router_edit_router',
                        'label' => 'Editar router',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 3
                    ],
                    'router_delete_router' => [
                        'field' => 'router_delete_router',
                        'label' => 'Eliminar router',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 4
                    ],
                ]),
                'position' => 5
            ],
            'router_view_router' => [
                'include' => false,
            ],
            'router_add_router' => [
                'include' => false,
            ],
            'router_edit_router' => [
                'include' => false,
            ],
            'router_delete_router' => [
                'include' => false,
            ],

            //Administracion
            'administration_administration' => [
                'partition' => 'user',
                'label' => 'Panel de administración',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'administration_view_administration' => [
                        'field' => 'administration_view_administration',
                        'label' => 'Ver Panel de administración',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                ]),
                'position' => 1
            ],
            'administration_view_administration' => [
                'include' => false,
            ],

            'user_user' => [
                'partition' => 'user',
                'label' => 'Usuarios',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'user_view_user' => [
                        'field' => 'user_view_user',
                        'label' => 'Ver Listado de Usuarios',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                    'user_add_user' => [
                        'field' => 'user_add_user',
                        'label' => 'Agregar usuario',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 2
                    ],
                    'user_edit_user' => [
                        'field' => 'user_edit_user',
                        'label' => 'Editar usuario',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 3
                    ],
                    'user_delete_user' => [
                        'field' => 'user_delete_user',
                        'label' => 'Eliminar usuario',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 4
                    ],
                ]),
                'position' => 2
            ],
            'user_view_user' => [
                'include' => false,
            ],
            'user_add_user' => [
                'include' => false,
            ],
            'user_edit_user' => [
                'include' => false,
            ],
            'user_delete_user' => [
                'include' => false,
            ],

            'rol_rol' => [
                'partition' => 'user',
                'label' => 'Roles',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'rol_view_rol' => [
                        'field' => 'rol_view_rol',
                        'label' => 'Ver Listado de roles',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                    'rol_add_rol' => [
                        'field' => 'rol_add_rol',
                        'label' => 'Agregar rol',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 2
                    ],
                    'rol_edit_rol' => [
                        'field' => 'rol_edit_rol',
                        'label' => 'Editar rol',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 3
                    ],
                    'rol_delete_rol' => [
                        'field' => 'rol_delete_rol',
                        'label' => 'Eliminar rol',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 4
                    ],
                ]),
                'position' => 3
            ],
            'rol_view_rol' => [
                'include' => false,
            ],
            'rol_add_rol' => [
                'include' => false,
            ],
            'rol_edit_rol' => [
                'include' => false,
            ],
            'rol_delete_rol' => [
                'include' => false,
            ],

            'partner_partner' => [
                'partition' => 'user',
                'label' => 'Socios',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'partner_view_partner' => [
                        'field' => 'partner_view_partner',
                        'label' => 'Ver Listado de socios',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                    'partner_add_partner' => [
                        'field' => 'partner_add_partner',
                        'label' => 'Agregar socio',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 2
                    ],
                    'partner_edit_partner' => [
                        'field' => 'partner_edit_partner',
                        'label' => 'Editar socio',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 3
                    ],
                    'partner_delete_partner' => [
                        'field' => 'partner_delete_partner',
                        'label' => 'Eliminar socio',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 4
                    ],
                ]),
                'position' => 4
            ],
            'partner_view_partner' => [
                'include' => false,
            ],
            'partner_add_partner' => [
                'include' => false,
            ],
            'partner_edit_partner' => [
                'include' => false,
            ],
            'partner_delete_partner' => [
                'include' => false,
            ],

            'location_location' => [
                'partition' => 'user',
                'label' => 'Ubicación',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'location_view_location' => [
                        'field' => 'location_view_location',
                        'label' => 'Ver Listado de ubicaciones',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                    'location_add_location' => [
                        'field' => 'location_add_location',
                        'label' => 'Agregar ubicación',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 2
                    ],
                    'location_edit_location' => [
                        'field' => 'location_edit_location',
                        'label' => 'Editar ubicación',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 3
                    ],
                    'location_delete_location' => [
                        'field' => 'location_delete_location',
                        'label' => 'Eliminar ubicación',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 4
                    ],
                ]),
                'position' => 5
            ],
            'location_view_location' => [
                'include' => false,
            ],
            'location_add_location' => [
                'include' => false,
            ],
            'location_edit_location' => [
                'include' => false,
            ],
            'location_delete_location' => [
                'include' => false,
            ],

            'state_state' => [
                'partition' => 'user',
                'label' => 'Estado',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'state_view_state' => [
                        'field' => 'state_view_state',
                        'label' => 'Ver Listado de estados',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                    'state_add_state' => [
                        'field' => 'state_add_state',
                        'label' => 'Agregar estado',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 2
                    ],
                    'state_edit_state' => [
                        'field' => 'state_edit_state',
                        'label' => 'Editar estado',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 3
                    ],
                    'state_delete_state' => [
                        'field' => 'state_delete_state',
                        'label' => 'Eliminar estado',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 4
                    ],
                ]),
                'position' => 6
            ],
            'state_view_state' => [
                'include' => false,
            ],
            'state_add_state' => [
                'include' => false,
            ],
            'state_edit_state' => [
                'include' => false,
            ],
            'state_delete_state' => [
                'include' => false,
            ],

            'municipality_municipality' => [
                'partition' => 'user',
                'label' => 'Municipio',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'municipality_view_municipality' => [
                        'field' => 'municipality_view_municipality',
                        'label' => 'Ver Listado de municipios',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                    'municipality_add_municipality' => [
                        'field' => 'municipality_add_municipality',
                        'label' => 'Agregar municipio',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 2
                    ],
                    'municipality_edit_municipality' => [
                        'field' => 'municipality_edit_municipality',
                        'label' => 'Editar municipio',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 3
                    ],
                    'municipality_delete_municipality' => [
                        'field' => 'municipality_delete_municipality',
                        'label' => 'Eliminar municipio',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 4
                    ],
                ]),
                'position' => 7
            ],
            'municipality_view_municipality' => [
                'include' => false,
            ],
            'municipality_add_municipality' => [
                'include' => false,
            ],
            'municipality_edit_municipality' => [
                'include' => false,
            ],
            'municipality_delete_municipality' => [
                'include' => false,
            ],

            'colony_colony' => [
                'partition' => 'user',
                'label' => 'Colonia',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'colony_view_colony' => [
                        'field' => 'colony_view_colony',
                        'label' => 'Ver Listado de colonias',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                    'colony_add_colony' => [
                        'field' => 'colony_add_colony',
                        'label' => 'Agregar colonia',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 2
                    ],
                    'colony_edit_colony' => [
                        'field' => 'colony_edit_colony',
                        'label' => 'Editar colonia',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 3
                    ],
                    'colony_delete_colony' => [
                        'field' => 'colony_delete_colony',
                        'label' => 'Eliminar colonia',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 4
                    ],
                ]),
                'position' => 8
            ],
            'colony_view_colony' => [
                'include' => false,
            ],
            'colony_add_colony' => [
                'include' => false,
            ],
            'colony_edit_colony' => [
                'include' => false,
            ],
            'colony_delete_colony' => [
                'include' => false,
            ],

            'payment_method' => [
                'partition' => 'user',
                'label' => 'Método de pago',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'payment_view_method' => [
                        'field' => 'payment_view_method',
                        'label' => 'Ver Método de Pago',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                ]),
                'position' => 9
            ],
            'payment_view_method' => [
                'include' => false,
            ],

            'ift_ift' => [
                'partition' => 'user',
                'label' => 'Ift',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'ift_view_ift' => [
                        'field' => 'ift_view_ift',
                        'label' => 'Ver Ift',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                ]),
                'position' => 10
            ],
            'ift_view_ift' => [
                'include' => false,
            ],

            'remove_service' => [
                'partition' => 'user',
                'label' => 'Remover servicios de clientes',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'remove_view_service' => [
                        'field' => 'remove_view_service',
                        'label' => 'Ver vista',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                ]),
                'position' => 11
            ],
            'remove_view_service' => [
                'include' => false,
            ],

            //Configuración
            'recurring_debts_payments' => [
                'partition' => 'setting',
                'label' => 'Panel de configuración',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'setting_view_setting' => [
                        'field' => 'setting_view_setting',
                        'label' => 'Ver dashboard de configuración',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                    'recurring_debts_payments_add' => [
                        'field' => 'recurring_debts_payments_add',
                        'label' => 'Agregar pago de para clientes recurrentes',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 2
                    ],
                ]),
                'position' => 3
            ],
            'setting_view_setting' => [
                'include' => false,
            ],
            'recurring_debts_payments_add' => [
                'include' => false,
            ],

            'recurring_debts_payments_custom' => [
                'partition' => 'setting',
                'label' => 'Pago de deudas para clientes custom',
                'placeholder' => '',
                'type' => 'input-checkbox-with-inputs',
                'value' => false,

                'depend' => true,
                'inputs_depend' => json_encode([
                    'recurring_debts_payments_custom_view' => [
                        'field' => 'recurring_debts_payments_custom_view',
                        'label' => 'Ver Listado de Pagos',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 1
                    ],
                    'recurring_debts_payments_custom_add' => [
                        'field' => 'recurring_debts_payments_custom_add',
                        'label' => 'Agregar pago',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 2
                    ],
                    'recurring_debts_payments_custom_edit' => [
                        'field' => 'recurring_debts_payments_custom_edit',
                        'label' => 'Editar pago',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 3
                    ],
                    'recurring_debts_payments_custom_destroy' => [
                        'field' => 'recurring_debts_payments_custom_destroy',
                        'label' => 'Eliminar pago',
                        'placeholder' => '',
                        'type' => 'input-checkbox',
                        'value' => false,
                        'position' => 4
                    ],
                ]),
                'position' => 5
            ],
            'recurring_debts_payments_custom_view' => [
                'include' => false,
            ],
            'recurring_debts_payments_custom_add' => [
                'include' => false,
            ],
            'recurring_debts_payments_custom_edit' => [
                'include' => false,
            ],
            'recurring_debts_payments_custom_destroy' => [
                'include' => false,
            ],
        ],
    ],
];
