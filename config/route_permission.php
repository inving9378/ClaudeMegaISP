<?php
return [
    //Dashboard
    'dashboard_view_dashboard' => ['/', '/home', '/index', '/dashboard'],
    'dashboard_view_card_client_inline' => ['/', '/home', '/index', '/dashboard'],
    'dashboard_view_card_client_new' => ['/', '/home', '/index', '/dashboard'],
    'dashboard_view_card_tickets_open_new' => ['/', '/home', '/index', '/dashboard'],
    'dashboard_view_card_device_not_responding' => ['/', '/home', '/index', '/dashboard'],
    'dashboard_view_block_client' => ['/', '/home', '/index', '/dashboard'],
    'dashboard_view_block_ticket' => ['/', '/home', '/index', '/dashboard'],
    'dashboard_view_block_finance' => ['/', '/home', '/index', '/dashboard'],
    'dashboard_view_info_invoice_transaction' => ['/', '/home', '/index', '/dashboard'],
    'dashboard_view_block_server_status' => ['/', '/home', '/index', '/dashboard'],

    'release_view_release' => ['/releases', '/releases/{version}'],
    'release_add_release' => ['/releases/store'],
    'release_edit_release' => ['/releases/update/{id}'],
    'release_add_description' => ['/releases/description/store'],
    'release_edit_description' => ['/releases/description/update/{id}'],
    'release_delete_description' => ['/releases/description/delete/{id}'],

    //Plan
    'plan_view_internet' => ['/internet', '/internet/table'],
    'plan_add_internet' => ['/internet/crear', '/internet/add', '/internet/success/{id}'],
    'plan_edit_internet' => ['/internet/editar/{id}', '/internet/update/{id}', '/internet/success/{id}'],
    'plan_delete_internet' => ['/internet/destroy/{id}'],

    'plan_view_voz' => ['/voz', '/voz/table'],
    'plan_add_voz' => ['/voz/crear', '/voz/add', '/voz/success/{id}'],
    'plan_edit_voz' => ['/voz/editar/{id}', '/voz/update/{id}', '/voz/success/{id}'],
    'plan_delete_voz' => ['/voz/destroy/{id}'],

    'plan_view_custom' => ['/custom', '/custom/table'],
    'plan_add_custom' => ['/custom/crear', '/custom/add', '/custom/success/{id}'],
    'plan_edit_custom' => ['/custom/editar/{id}', '/custom/update/{id}', '/custom/success/{id}'],
    'plan_delete_custom' => ['/custom/destroy'],

    'plan_view_package' => ['/paquetes', '/paquetes/table'],
    'plan_add_package' => ['/paquetes/crear', '/paquetes/add', '/paquetes/success/{id}'],
    'plan_edit_package' => ['/paquetes/editar/{id}', '/paquetes/update/{id}', '/paquetes/success/{id}'],
    'plan_delete_package' => ['/paquetes/destroy/{id}'],

    // Catálogo de Servicios Contratables (Planes → Servicios contratables)
    'contratables.manage' => [
        '/planes/contratables', '/planes/contratables/data', '/planes/contratables/modulos',
        '/planes/contratables/crear', '/planes/contratables/editar/{id}', '/planes/contratables/show/{id}',
        '/planes/contratables/add', '/planes/contratables/update/{id}', '/planes/contratables/destroy/{id}',
        '/cliente/contratables/{clientId}/data', '/cliente/contratables/{clientId}/activar',
        '/cliente/contratables/{clientId}/suspender', '/cliente/contratables/{clientId}/reactivar',
    ],

    //Crm
    'crm_view_crm' => ['/crm/listar', '/crm/table'],
    'crm_add_crm' => ['/crm/crear', '/crm/add', '/crm/success/{id}'],
    'crm_edit_crm' => ['/crm/update/{id}', '/crm/success/{id}', '/crm/editar/{id}', '/crm/information/{crmId}/get-crm-main-information-id-and-crm-lead-information-id', '/crm/update-last-contacted/{id}'],
    'crm_delete_crm' => ['/crm/destroy/{id}'],
    'crm_convert_crm' => ['/crm/view-of-convert-crm-to-client/{id}', '/crm/convert-to-client/{id}'],
    'crm_export_crm' => ['/crm/table'],


    'crm_document_view_crm' => ['/crm/document/listar', '/crm/document/table', '/crm/send-notification/{id}'],
    'crm_document_add_crm' => ['/crm/document/crear', '/crm/document/add/{idCrm}', '/crm/document/success/', '/crm/document/upload-file/{id}', '/crm/document/generate_contract/{id}', '/crm/document/load_content_template', '/crm/document/show_content_template'],
    'crm_document_edit_crm' => ['/crm/document/editar', '/crm/document/update/{idCrm}', '/crm/document/success/', '/crm/document/upload-file/{id}'],
    'crm_document_delete_crm' => ['/crm/document/destroy/{id}'],

    //Client
    'client_view_dashboard' => ['/cliente', '/cliente/get-data-client-to-select-component/{id}'],
    'client_view_client' => ['/cliente/listar', '/cliente/table', '/cliente/get-client-with-balance/{id}', '/cliente/get-tickets-open/{id}', '/configuracion/template-task/get-data-template/{id}', '/cliente/billing/get-type-of-billing-by-client-id/{id}', '/cliente/get-client-id-by-client-main-information-id/{id}'],
    'client_add_client' => ['/cliente/crear', '/cliente/add', '/cliente/success/{id}'],
    'client_edit_client' => [
        '/cliente/editar/{id}',
        '/cliente/update/{id}',
        '/cliente/success/',
        '/cliente/{id}/get-client-main-information-id-and-client-additional-information-id',
        '/cliente/can-add-service/{id}',
        '/cliente/document/generate_contract/{id}',
        '/cliente/document/table',
        '/cliente/has-service/{id}',
        '/cliente/billing/update-billing-configuration/{id}',
        '/cliente/get-client-status/{id}',
        '/cliente/get-promotions/{id}',
        '/cliente/get-period-by-amount/{id}',
        '/cliente/bulk-plan-promotion',
        '/cliente/without-data-promotions',
        '/cliente/with-data-promotions/{id}',
        '/administracion/user/avaiables-promotions/{code}',
    ],
    'client_delete_client' => ['/cliente/destroy/{id}'],
    'client_edit_fecha_corte' => [
        '/cliente/edit_court_date'
    ],
    'client_edit_fecha_pago' => [
        '/cliente/edit_date_payment'
    ],
    'client_update_status' => [],

    'client_edit_balance' => [
        '/cliente/edit_balance'
    ],
    'client_edit_id' => [
        '/cliente/edit_id'
    ],

    'client_service_internet_view_client' => [
        '/cliente/clientinternetservice/table',
        '/cliente/get-client-filtered-by-internet-service/{id}'
    ],
    'client_service_internet_add_client' => ['/cliente/clientinternetservice/crear/{id}'],
    'client_service_internet_edit_client' => [
        '/cliente/clientinternetservice/update/{id}',
        '/cliente/clientinternetservice/change-internet/{id}'
    ],
    'client_service_internet_delete_client' => ['/cliente/clientinternetservice/destroy/{id}'],

    'client_service_voz_view_client' => [
        '/cliente/clientvozservice/table',
        '/cliente/get-client-filtered-by-voz-service/{id}'
    ],
    'client_service_voz_add_client' => ['/cliente/clientvozservice/crear/{id}'],
    'client_service_voz_edit_client' => ['/cliente/clientvozservice/update/{id}'],
    'client_service_voz_delete_client' => ['/cliente/clientvozservice/destroy/{id}'],

    'client_service_bundle_view_client' => [
        '/cliente/clientbundleservice/table',
        '/cliente/get-client-filtered-by-bundle-service/{id}',
    ],
    'client_service_bundle_add_client' => [
        '/cliente/clientbundleservice/crear/{id}',
        '/cliente/clientbundleservice/bundle/{id}'
    ],
    'client_service_bundle_edit_client' => [
        '/cliente/clientbundleservice/update/{id}',
        '/cliente/clientbundleservice/bundle/edit/{id}'
    ],
    'client_service_bundle_delete_client' => [
        '/cliente/clientbundleservice/destroy/{id}'
    ],
    'client_service_bundle_change_client' => [
        '/cliente/clientbundleservice/bundle/get-plans-to-change/{id}',
        '/cliente/clientbundleservice/bundle/get-equals/{id}',
        '/cliente/clientbundleservice/bundle/change-bundle/{id}'
    ],


    ///Custom
    'client_service_custom_view_client' => [
        '/cliente/clientcustomservice/table',
        '/cliente/get-client-filtered-by-custom-service/{id}',
    ],
    'client_service_custom_add_client' => ['/cliente/clientcustomservice/crear/{id}'],
    'client_service_custom_edit_client' => ['/cliente/clientcustomservice/update/{id}'],
    'client_service_custom_delete_client' => ['/cliente/clientcustomservice/destroy/{id}'],

    'client_document_view_client' => ['/cliente/document/listar', '/cliente/document/table'],
    'client_document_add_client' => ['/cliente/document/add/{idClient}', '/cliente/document/generate_contract/{id}', '/cliente/document/load_content_template', '/cliente/document/show_content_template', '/cliente/document/upload-file/{id}'],
    'client_document_edit_client' => ['/cliente/document/update/{idClient}', '/cliente/document/generate_contract/{id}', '/cliente/document/load_content_template', '/cliente/document/show_content_template', '/cliente/document/upload-file/{id}'],
    'client_document_delete_client' => ['/cliente/document/destroy{id}'],

    //Pagos
    'client_billing_view_tab_client' => [
        '/cliente/billing/get-billing-information-block/{id}',
        '/cliente/billing/get-reminder-payment-amount/{id}',
        '/cliente/debit/{id}',
        '/cliente/billing/payment/get-cost-all-service-active/{id}',
        '/cliente/billing/payment/get-totals/{id}',
        '/cliente/billing/get-payment-method/{id}',
        '/cliente/billing/payment/get-active-service-expiration/{id}',
        '/cliente/billing/payment/get-cost-all-service/{id}',
        '/cliente/get-is-promise-payment/{id}',
        '/finanzas/invoices/get-available-periods-by-client/{id}'
    ],
    'client_payroll_payment_add_client' => [
        '/cliente/billing/payment/crear/{id}',
    ],
    'client_payroll_payment_view_client' => [
        '/cliente/billing/payment/table',
    ],
    'client_payroll_payment_edit_client' => [
        'cliente/billing/payment/update/{id}'
    ],
    'client_payroll_payment_delete_client' => [
        '/cliente/billing/payment/destroy/{id}'
    ],
    //Transacciones
    'client_billing_transaction_client' => [
        '/cliente/billing/transaction/table',
        '/cliente/billing/transaction/get-totals/{id}'
    ],
    'client_billing_transaction_add' => [
        '/cliente/billing/transaction/crear/{id}'
    ],
    'client_billing_transaction_edit' => [
        '/cliente/billing/transaction/update/{id}'
    ],
    'client_billing_transaction_delete' => [
        '/cliente/billing/transaction/destroy/{id}'
    ],

    //Facturas
    'client_billing_invoice_client' => [
        '/cliente/billing/invoice/table',
        '/cliente/billing/invoice/get-totals/{id}',
        '/cliente/billing/invoice/pdf/{id}',
        '/finanzas/invoices/table',
        '/finanzas/invoices/get-pending-by-client/{id}'
    ],
    'client_billing_invoice_add' => [
        '/cliente/billing/invoice/crear/{id}',
        '/cliente/billing/invoice/create-new-client-invoice/{id}',
        '/finanzas/invoices/create-for-client/{id}'
    ],
    'client_billing_invoice_edit' => [
        '/cliente/billing/invoice/update/{id}'
    ],
    'client_billing_invoice_delete' => [
        '/cliente/billing/invoice/destroy/{id}'
    ],

    // Vendedores
    'seller_view_seller' => [
        '/vendedores',
        '/configuracion/comisiones/{id}/get-commissions-pending',
        '/vendedores/data',
        '/vendedores/data',
        '/configuracion/comisiones/{id}/get-commissions-pending',
        '/sellers/seller',
        '/sellers/seller/table',
        '/vendedores/{seller}/seguimiento-vendedor/{user}'
    ],
    'seller_view_billing' => [
        '/vendedores/payments-sellers/pending-payments-by-seller/{seller}',
    ],
    'seller_view_panel' => [
        '/vendedores/seguimiento-me',
        '/vendedores/{id}/getDataById',
        '/configuracion/comisiones/{id}/get-total-amount-commission',
        '/vendedores/get-type-sellers',
        '/vendedores/get-status-sellers',
        '/configuracion/comisiones/{id}/get-commissions-pending',
        '/vendedores/ventas/sales-by-seller/{id}',
        '/vendedores/payments/{id}/getRuleDataSeller',
        '/vendedores/payments/get-periods-from-seller/{id}',
        '/vendedores/payments/{id}/getMontlyCommissionsBySeller',
        '/vendedores/payments-sellers/discount-account/{id}',
        '/vendedores/payments-sellers/expenses-account/{id}',
        '/vendedores/payments-sellers/incomes-account/{id}',
        '/vendedores/payments-sellers/debt-account/{id}',
        '/vendedores/payments-sellers/pending-discounts/{id}',
        '/vendedores/payments-sellers/payments-by-seller/{id}',
        '/vendedores/payments-sellers/discounts-by-seller/{id}',
        '/administracion/user/get-all-users',
        '/inventory/inventory_item/add_movement',
        '/sellers/cuts/get-user-current-box/{id}',
    ],
    'seller_view_dashboard' => ['/vendedores/dashboard', '/vendedores/seguimiento-me', '/vendedores/{id}/getDataById', '/configuracion/metodos-de-pago/get-all-methods'],
    'seller_view_statics' => ['/vendedores/salesAndProspects/{startDate}/{endDate}/{id}', '/vendedores/{id}/compareSales', '/vendedores/salesByMedium/{startDate}/{endDate}/{id}', '/vendedores/{id}/prospectsByStatus/{startDate}/{endDate}'],
    'seller_view_sales' => ['/vendedores/ventas/{id}/salesBySeller'],
    'seller_follow_payment_client' => ['/vendedores/payments/{id}/getDataSeller', '/configuracion/reglas-comisiones/get-rule-by-seller/{id}', '/vendedores/payments/{id}/getListPayments'],
    'seller_view_all_payments_for_seller' => ['/vendedores/payments-sellers/{id}/get-all-payments-of-seller'],
    'seller_view_all_transactions_for_seller' => ['/vendedores/transacciones/{id}/{startDate}/{endDate}/{methodPayment}/get-transactions-by-seller'],
    'seller_add_payment' => ['/vendedores/ventas/{id}/salesBySeller'],

    'selleritems_view_selleritems' => [
        '/inventory/inventory_item_stock/get_items_by_user/{id}',
        '/inventory/inventory_item_stock/accept_item_by_movement/{id}',
        '/inventory/inventory_item_stock/reject_item_by_movement/{id}',
        '/inventory/inventory_item_stock/get_items_by_store/{id}'
    ],
    'selleritems_add_selleritems' => [],
    'selleritems_edit_selleritems' => [],
    'selleritems_delete_selleritems' => [],
    'seller_cuts' => [
        '/sellers/cuts/suppliers-expenses-list/{id}',
        '/sellers/cuts/extras-incomes-list/{id}',
        '/sellers/cuts/installations-list/{id}',
        '/sellers/cuts/observations-list/{id}',
        '/sellers/cuts/get-user-current-box/{id}',
        '/sellers/cuts/get-received-payments-by-box/{id}',
        '/administracion/sucursal/all',
        '/sellers/cuts/technicals',
        '/sellers/cuts/{id}',
        '/sellers/cuts/box/{id}',
        '/sellers/cuts/box-pdf/{id}',
        '/cliente/actives'
    ],
    'seller_cuts_add_installation' => [
        '/sellers/cuts/installations'
    ],
    'seller_cuts_edit_installation' => [
        '/sellers/cuts/installations/{id}'
    ],
    'seller_cuts_delete_installation' => [
        '/sellers/cuts/installations/{id}'
    ],
    'seller_cuts_add_expenses' => [
        '/sellers/cuts/suppliers-expenses'
    ],
    'seller_cuts_edit_expenses' => [
        '/sellers/cuts/suppliers-expenses/{id}'
    ],
    'seller_cuts_delete_expenses' => [
        '/sellers/cuts/suppliers-expenses/{id}'
    ],
    'seller_cuts_add_extra_icome' => [
        '/sellers/cuts/extras-incomes'
    ],
    'seller_cuts_edit_extra_icome' => [
        '/sellers/cuts/extras-incomes/{id}'
    ],
    'seller_cuts_delete_extra_icome' => [
        '/sellers/cuts/extras-incomes/{id}'
    ],
    'seller_cuts_add_comments' => [
        '/sellers/cuts/observations'
    ],
    'seller_cuts_edit_comments' => [
        '/sellers/cuts/observations/{id}'
    ],
    'seller_cuts_delete_comments' => [
        '/sellers/cuts/observations/{id}'
    ],
    'seller_cuts_close_box' => [
        '/sellers/cuts/close-user-current-box/{id}'
    ],

    //Ticket
    'ticket_view_dashboard' => [
        '/tickets',
        '/tickets/notifica/{id}',
        '/tickets/get-ticket-by-id/{id}',
        '/tickets/get-time-lapsed/{id}',
        '/tickets/get-user-data-by-ticket-id/{id}',
        '/tickets/set-status-ticket-by-id/{id}',
        '/tickets/get-ticket-thread-by-id/{id}',
        '/tickets/get-data-client/{id}',
        '/tickets/get-parent-ticket-by-id/{id}',
        '/tickets/get-child-ticket-by-id/{id}',
        '/tickets/request-statistics-for-tarjets-by-status',
        '/tickets/request-ticket-assigned-to-me',
        '/tickets/request-ticket-assigned-to',
        '/tickets/get-tickets-new-by-date/{startDate}/{endDate}/{status}',
        '/tickets/ver/{id}'
    ],

    'ticket_view_open' => ['/tickets/abiertos/{client_id}', '/tickets/table', '/tickets/abiertos'],
    'ticket_add_open' => ['/tickets/crear/{clientId}', '/tickets/add', '/tickets/success/{id}', '/tickets/mensaje/add/{id}'],
    'ticket_edit_open' => ['/tickets/editar', '/tickets/update', '/tickets/mensaje/update/{id}'],
    'ticket_delete_open' => ['/tickets/destroy/{id}'],

    'ticket_view_close' => ['/tickets/cerrados', '/tickets/table', '/tickets/cerrados/{client_id}'],
    'ticket_add_close' => ['/tickets/crear', '/tickets/add', '/tickets/success', '/tickets/mensaje/add'],
    'ticket_edit_close' => ['/tickets/editar', '/tickets/update', '/tickets/mensaje/update',],
    'ticket_delete_close' => ['/tickets/destroy'],

    'ticket_view_recycling' => ['/tickets/reciclados', '/tickets/table'],
    'ticket_add_recycling' => ['/tickets/crear', '/tickets/add', '/tickets/success', '/tickets/mensaje/add'],
    'ticket_edit_recycling' => ['/tickets/editar', '/tickets/update', '/tickets/mensaje/update',],
    'ticket_delete_recycling' => ['/tickets/destroy/{id}'],

    // Finanzas
    'finance_view_transactions' => ['/finanzas/transacciones', '/finanzas/transacciones/table',],
    'finance_view_billing' => ['/finanzas/facturas', '/finanzas/facturas/table', 'cliente/billing/invoice/pdf/{id}'],
    'finance_view_payments' => ['/finanzas/pagos', '/finanzas/pagos/table', '/cliente/billing/payment/pdf/{id}'],
    'finance_view_invoices' => [
        '/finanzas/invoices',
        '/finanzas/invoices/table',
        '/finanzas/invoices/get-pending-by-client/{id}',
        '/finanzas/invoices/get-available-periods-by-client/{id}'
    ],
    'invoice_add_invoice' => ['/finanzas/invoices/create-for-client/{id}'],
    'invoice_send_invoice' => ['/finanzas/invoices/send/{id}'],
    'invoice_mark_as_paid_invoice' => ['/finanzas/invoices/mark-as-paid/{id}'],
    'invoice_print_invoice' => ['/finanzas/invoices/print/{id}'],
    'invoice_delete_invoice' => ['/finanzas/invoices/destroy/{id}'],

    'finance_view_general_accounting' => [
        '/finanzas/general-accounting',
        '/finanzas/general-accounting/get-data',
        '/finanzas/general-accounting/get-bar-data',
        '/finanzas/general-accounting/get-donut-data',
        '/finanzas/category/add'
    ],
    'finance_view_general_accounting_income' => [
        '/finanzas/general-accounting/income',
        '/finanzas/general-accounting/income/table',
    ],
    'finance_add_general_accounting_income' => [
        '/finanzas/general-accounting/income/add',
        '/finanzas/general-accounting/operation/add',
    ],
    'finance_edit_general_accounting_income' => [
        '/finanzas/general-accounting/income/update/{id}',
    ],
    'finance_delete_general_accounting_income' => [
        '/finanzas/general-accounting/income/delete/{id}',
    ],
    'finance_view_general_accounting_expense' => [
        '/finanzas/general-accounting/expense',
        '/finanzas/general-accounting/expense/table',
    ],
    'finance_add_general_accounting_expense' => [
        '/finanzas/general-accounting/expense/add',
        '/finanzas/general-accounting/operation/add',
    ],
    'finance_edit_general_accounting_expense' => [
        '/finanzas/general-accounting/expense/update/{id}',
    ],
    'finance_delete_general_accounting_expense' => [
        '/finanzas/general-accounting/income/delete/{id}',
    ],
    //OLTs
    'gestion-red.provision.preview' => [
        '/olts/provision/preview',
    ],

    'olt_view' => [
        '/olts',
        '/olts/list',
        '/olts/zones',
        '/olts/type-onus',
        '/olts/nomenclatures',
        '/olts/onus-nomenclatures',
        '/olts/uptime-env-temp',
        '/olts/dashboard-onus-status/{id}',
        '/olts/dashboard-onus-status',
        '/olts/outage-pons',
        '/olts/outage-pons/{id}',

        '/olts/onus/configured',
        '/olts/onus/configured/{id}',
        '/olts/onus/saved-unconfigured',
        '/olts/onus/unconfigured',
        '/olts/onus/unconfigured/{id}',
        '/olts/onus/traffic-graph/{id}',
        '/olts/onus/image/{id}',
        '/olts/onus/signal-graph/{id}',
        '/olts/onus/nomenclatures',
        '/olts/onus/details/{id}',
        '/olts/onus/signal/{id}',
        '/olts/onus/get-by-client/{id}',
        '/olts/onus/get-mgmt-ip/{id}',
        '/olts/onus/get-ip-address/{id}',
        '/olts/onus/get-status-and-signal/{id}',
        '/olts/onus/sync/{id}',
        '/olts/onus/full-status/{id}',
        '/olts/onus/running-config/{id}',
        '/olts/onus/change-web-user-pass/{id}',
        '/olts/onus/set-catv/{id}',

        '/olts/settings/odbs',
        '/olts/settings/zones',
        '/olts/settings/type-onus',
        '/olts/settings/profiles',
        '/olts/settings/olts',
        '/olts/settings/billings',
        '/olts/settings/olts/{id}/cards',
        '/olts/settings/olts/{id}/pon-ports',
        '/olts/settings/olts/{id}/uplink-ports',
        '/olts/settings/olts/{id}/vlans',
    ],

    'sync_from_api' => [],

    'onu_add' => [
        '/olts/onus/create',
    ],

    'onu_edit' => [
        '/olts/onus/move/{id}',
        '/olts/onus/update-location/{id}',
        '/olts/onus/update-external-id/{id}',
        '/olts/onus/change-onu-type/{id}',
        '/olts/onus/update-attached-vlans/{id}',
        '/olts/onus/update-service-port/{id}',
        '/olts/onus/configure-ethernet-port/{id}',
        '/olts/onus/configure-wifi-port/{id}',
        '/olts/onus/set-onu-voip-port/{id}',
        '/olts/onus/update-channel/{id}',
        '/olts/onus/update-mode/{id}',
        '/olts/onus/update-mgmt-and-vo-ip/{id}',
    ],

    'onu_default' => [],

    'onu_remove' => [
        '/olts/onus/remove/{id}',
    ],

    'onu_enable_disable' => [
        '/olts/onus/enable-disable/{id}',
    ],

    'onu_resync' => [
        '/olts/onus/resync/{id}',
    ],

    'onu_reboot' => [
        '/olts/onus/reboot/{id}',
    ],

    'olt_zones' => [
        '/olts/settings/zones',
    ],

    'zone_add' => [
        '/olts/settings/zones/store',
    ],

    'odb_add' => [
        '/olts/settings/zones/store',
    ],

    'vlan_add' => [
        '/olts/settings/olts/{id}/vlans/store',
    ],

    'onu_type_add' => [
        '/olts/settings/type-onus/store',
    ],


    //Mapas
    'maps_view_maps' => [
        '/mapas',
        '/maps/projects',
        '/configuracion/credenciales-google-maps/edit',
        '/maps/layers/configuration/{id}',
        '/maps/zones',
    ],

    'maps_change_classification' => [
        '/maps/change-classification',
    ],

    'maps_kmz_load' => [
        '/maps/kmz/{id}',
        '/maps/kmz',
    ],

    'maps_kmz_edit' => [
        '/maps/layers/{id}',
        '/maps/layers/coords/{id}'
    ],

    'maps_kmz_remove' => [
        '/maps/layers/{id}'
    ],

    'maps_folder_add' => [
        '/maps/projects',
    ],

    'maps_folder_edit' => [
        '/maps/projects/{id}',
        '/maps/projects/move-folder/{node}/{to}',
        '/maps/projects/move-marker/{node}/{to}',
        '/maps/projects/move-folder/{node}',
        '/maps/projects/move-marker/{node}',
        '/maps/layers/convert-from-project/{id}',
        '/maps/layers/convert-from-layer/{id}',
        '/maps/layers/convert-from-tickeds'
    ],

    'maps_folder_remove' => [
        '/maps/projects/{id}',
    ],

    'maps_region_add' => [
        '/maps/layers'
    ],

    'maps_region_edit' => [
        '/maps/layers/{id}',
        '/maps/layers/coords/{id}'
    ],

    'maps_region_remove' => [
        '/maps/layers/{id}'
    ],

    'maps_route_add' => [
        '/maps/layers'
    ],

    'maps_route_edit' => [
        '/maps/layers/{id}',
        '/maps/layers/coords/{id}'
    ],

    'maps_route_remove' => [
        '/maps/layers/{id}'
    ],

    'maps_service_box_add' => [
        '/maps/layers'
    ],

    'maps_service_box_edit' => [
        '/maps/layers/{id}',
        '/maps/layers/coords/{id}',

        '/maps/service-box/selected-clients/{id}',
        '/maps/service-box/avaiables-clients',
        '/maps/service-box/remove-clients',
        '/maps/service-box/remove-client/{id}',
        '/maps/service-box/add-clients/{id}',
        '/maps/service-box/remove-client-from-drop/{id}',
        '/maps/devices',
        '/maps/devices/{id}',
        '/maps/devices/save-port/{id}',
        '/maps/connections',
        '/maps/connections/{id}',
        '/maps/connections-multiple/{id}',
        '/maps/connections/cut/{id}',
        '/maps/layers/unassign-route/{id}',
        '/maps/layers/avaiables-routes/{id}',
        '/maps/layers/avaiables-routes',
        '/maps/layers/assign-routes/{id}',
        '/maps/layers/change-route-position/{id}',
        '/maps/layers/create-input/{id}',
        '/maps/layers/update-input/{id}',
        '/maps/layers/update-markers-distance-from-route/{id}',
    ],

    'maps_service_box_remove' => [
        'maps/layers/{id}'
    ],

    'maps_junction_box_add' => [
        '/maps/layers'
    ],

    'maps_junction_box_edit' => [
        '/maps/layers/{id}',
        '/maps/layers/coords/{id}',

        '/maps/devices',
        '/maps/devices/{id}',
        '/maps/devices/save-port/{id}',
        '/maps/connections',
        '/maps/connections/{id}',
        '/maps/connections-multiple/{id}',
        '/maps/connections/cut/{id}',
        '/maps/layers/unassign-route/{id}',
        '/maps/layers/avaiables-routes/{id}',
        '/maps/layers/avaiables-routes',
        '/maps/layers/assign-routes/{id}',
        '/maps/layers/change-route-position/{id}',
        '/maps/layers/create-input/{id}',
        '/maps/layers/update-input/{id}',
        '/maps/layers/update-markers-distance-from-route/{id}',
    ],

    'maps_junction_box_remove' => [
        '/maps/layers/{id}'
    ],

    'maps_pack_add' => [
        '/maps/layers'
    ],

    'maps_pack_edit' => [
        '/maps/layers/{id}',
        '/maps/layers/coords/{id}',
        '/devices',
        '/devices/{id}',
        '/devices/save-port/{id}'
    ],

    'maps_pack_remove' => [
        '/maps/layers/{id}'
    ],

    'maps_cupboard_add' => [
        '/maps/layers'
    ],

    'maps_cupboard_edit' => [
        '/maps/layers/{id}',
        '/maps/layers/coords/{id}',

        '/maps/devices',
        '/maps/devices/{id}',
        '/maps/devices/add-ports/{id}',
        '/maps/devices/save-port/{id}',
        '/maps/devices/change-card-olt-direction/{id}',
        '/maps/connections',
        '/maps/connections/{id}',
        '/maps/connections-multiple/{id}',
        '/maps/connections/cut/{id}',
        '/maps/layers/unassign-route/{id}',
        '/maps/layers/avaiables-routes/{id}',
        '/maps/layers/avaiables-routes',
        '/maps/layers/assign-routes/{id}',
        '/maps/layers/change-route-position/{id}',
        '/maps/layers/create-input/{id}',
        '/maps/layers/update-input/{id}',
        '/maps/layers/update-markers-distance-from-route/{id}',
    ],

    'maps_cupboard_remove' => [
        '/maps/layers/{id}'
    ],

    'maps_source_add' => [
        '/maps/layers'
    ],

    'maps_source_edit' => [
        '/maps/layers/{id}',
        '/maps/layers/coords/{id}'
    ],

    'maps_source_remove' => [
        '/maps/layers/{id}'
    ],

    'maps_pole_add' => [
        '/maps/layers'
    ],

    'maps_pole_edit' => [
        '/maps/layers/{id}',
        '/maps/layers/coords/{id}'
    ],

    'maps_pole_remove' => [
        '/maps/layers/{id}'
    ],

    'maps_site_add' => [
        '/maps/layers'
    ],

    'maps_site_edit' => [
        '/maps/layers/{id}',
        '/maps/layers/coords/{id}',
        '/sites/racks',
        '/sites/racks/{id}',

        '/maps/devices',
        '/maps/devices/{id}',
        '/maps/devices/save-port/{id}',
        '/maps/devices/add-ports/{id}',
        '/maps/devices/change-card-olt-direction/{id}',
        '/maps/connections',
        '/maps/connections/{id}',
        '/maps/connections-multiple/{id}',
        '/maps/connections/cut/{id}',
        '/maps/layers/unassign-route/{id}',
        '/maps/layers/avaiables-routes/{id}',
        '/maps/layers/avaiables-routes',
        '/maps/layers/assign-routes/{id}',
        '/maps/layers/change-route-position/{id}',
        '/maps/layers/create-input/{id}',
        '/maps/layers/update-input/{id}',
        '/maps/layers/update-markers-distance-from-route/{id}',
    ],

    'maps_site_remove' => [
        '/maps/layers/{id}'
    ],

    'maps_building_add' => [
        '/maps/layers'
    ],

    'maps_building_edit' => [
        '/maps/layers/{id}',
        '/maps/layers/coords/{id}'
    ],

    'maps_building_remove' => [
        '/maps/layers/{id}'
    ],

    'maps_client_add' => [
        '/maps/layers'
    ],

    'maps_client_edit' => [
        '/maps/layers/{id}',
        '/maps/layers/coords/{id}'
    ],

    'maps_client_remove' => [
        '/maps/layers/{id}'
    ],

    'maps_note_add' => [
        '/maps/layers'
    ],

    'maps_note_edit' => [
        '/maps/layers/{id}',
        '/maps/layers/coords/{id}'
    ],

    'maps_note_remove' => [
        '/maps/layers/{id}'
    ],

    //Gestión de red
    'router_view_router' => ['/red/router', '/red/router/table', '/red/router/listar', '/configuracion/service_in_address_list'],
    'router_add_router' => [
        '/red/router/crear',
        '/red/router/add',
        '/red/router/success/{id}',
        '/red/router/mikrotik/crear/{id}',
        '/red/router/mikrotik/config/crear/{id}'
    ],
    'router_edit_router' => [
        '/red/router/editar/{id}',
        '/red/router/update/{id}',
        '/red/router/success/{id}',
        '/red/router/mikrotik/editar/{id}',
        '/red/router/mikrotik/update/{id}',
        '/red/router/mikrotik/config/editar/{id}',
        '/red/router/mikrotik/config/update/{id}',
        '/red/router/mikrotik/config/crear/{id}',
        '/red/router/mikrotik/cleantails',
        '/status-by-router/{id}',
        '/remove-rules-by-router/{id}',
        '/create-rules-by-router/{id}',
        '/request-clone-client-to-mikrotik/{id}'
    ],
    'router_delete_router' => [
        '/red/router/destroy/{id}',
        '/red/router/mikrotik/config/destroy/{id}',
        '/red/router/mikrotik/destroy/{id}'
    ],

    'ipv4_view_ipv4' => [
        '/red/ipv4',
        '/red/ipv4/table',
        '/red/ipv4/listar',
        '/red/ipv4/calculator'
    ],
    'ipv4_add_ipv4' => [
        '/red/ipv4/crear',
        '/red/ipv4/add'
    ],
    'ipv4_edit_ipv4' => [
        '/red/ipv4/editar/{id}',
        '/red/ipv4/update/{id}',
        '/red/ipv4/success',
        '/red/ipv4/ver/{id}',
        '/red/ipv4/network/{id}',
        '/red/ipv4/ip/table',
        '/red/ipv4/ip/update/{id}'
    ],
    'ipv4_delete_ipv4' => [
        '/red/ipv4/destroy/{id}'
    ],

    //Actividades Programadas
    //Proyectos
    'scheduling_project_view_project' => [
        '/scheduling/project',
        '/scheduling/project/table',
        '/scheduling/task/get-list-template-verification-by-task/{id}',
        '/configuracion/template-task/get-data-template/{id}',
        '/scheduling/project/{project}/users',
        '/scheduling/project/{project}/teams',
    ],
    'scheduling_project_create' => [
        '/scheduling/project/add'
    ],
    'scheduling_project_update' => [
        '/scheduling/project/update/{id}',
        '/scheduling/project/{project}/teams',
    ],
    'scheduling_project_delete' => [
        '/scheduling/project/destroy/{id}'
    ],
    //Tareas
    'task_view_full_task' => [
        '/scheduling/task',
        '/scheduling/task/table',
        '/scheduling/task/add',
        '/scheduling/task/update/{id}',
        '/scheduling/task/editar/{id}',
        '/scheduling/task/update-task-to-calendar',
        '/scheduling/task/destroy/{id}',
        '/scheduling/task/calendar',
        '/scheduling/task/update-task-to-calendar',
        '/scheduling/task/add_note/{id}',
        '/scheduling/task/get-notes-by-task/{id}',
        '/scheduling/task/get-data-task/{id}',
        '/scheduling/task/read-notification/{id}',
        '/scheduling/task/unread-notification/{id}',
        '/scheduling/task/show-archived',
        '/scheduling/task/download-file/{id}',
        '/scheduling/task/upload-file/{id}',
        '/scheduling/task/remove-file/{id}',
        '/configuracion/list-template-verification/get-check-list-template/{id}',

    ],

    'task_view_archived_task' => [
        '/scheduling/task/show-archived'
    ],
    'task_archive_task' => [
        '/scheduling/task/archived/{id}',
    ],

    'task_view_task' => [
        '/scheduling/task',
        '/scheduling/task/table'
    ],
    'task_add_task' => [
        '/scheduling/task/add'
    ],
    'task_edit_task' => [
        '/scheduling/task/update/{id}',
        '/scheduling/task/editar/{id}',
        '/scheduling/task/update-task-to-calendar',
        '/scheduling/task/add_note/{id}',
        '/scheduling/task/get-notes-by-task/{id}',
        '/scheduling/task/get-data-task/{id}',
        '/scheduling/task/download-file/{id}',
        '/scheduling/task/upload-file/{id}',
        '/scheduling/task/remove-file/{id}'
    ],
    'task_delete_task' => [
        '/scheduling/task/destroy/{id}'
    ],

    'task_filter_project' => [],
    'task_filter_status' => [],
    'task_filter_partner' => [],
    'task_filter_assigned_to' => [],
    'task_filter_filter' => [],

    //Calendario
    'scheduling_view_calendar' => [
        '/scheduling/task/calendar',
        '/scheduling/task/update-task-to-calendar'
    ],
    'calendar_filter_project' => [],
    'calendar_filter_status' => [],
    'calendar_filter_partner' => [],
    'calendar_filter_assigned_to' => [],
    'calendar_filter_filter' => [],

    //Administracion
    'admin_view_module' => ['/administracion'],

    'state_view_state' => ['/administracion/estado', '/administracion/estado/table'],
    'state_add_state' => ['/administracion/estado/add'],
    'state_edit_state' => ['/administracion/estado/editar/{id}', '/estado/update/{id}'],
    'state_delete_state' => ['/administracion/estado/destroy/{id}'],

    'municipality_view_municipality' => ['/administracion/municipio', '/administracion/municipio/table'],
    'municipality_add_municipality' => ['/administracion/municipio/add'],
    'municipality_edit_municipality' => ['/administracion/municipio/editar/{id}', '/municipio/update/{id}'],
    'municipality_delete_municipality' => ['/administracion/municipio/destroy/{id}'],

    'colony_view_colony' => ['/administracion/colonia', '/administracion/colonia/table'],
    'colony_add_colony' => ['/administracion/colonia/add'],
    'colony_edit_colony' => ['/administracion/colonia/editar/{id}', '/colonia/update/{id}'],
    'colony_delete_colony' => ['/administracion/colonia/destroy/{id}'],

    //inventario
    'inventory_view_inventory' => [
        '/inventory',
        '/inventory/inventory_store/get-all',
        '/inventory/store_zone/get-store-zones-by-store/{id}',
        '/inventory/inventory_item_stock/change_stock',
        '/inventory/inventory_item/add_movement'
    ],
    'inventory_item_view_inventory_item' => [
        '/inventory/inventory_item',
        '/inventory/inventory_item/table',
        '/inventory/inventory_item_stock',
        '/inventory/inventory_item_stock/table',
        '/inventory/inventory_item_stock/get_media_by_item/{id}',
        '/inventory/inventory_item_custom/items/{id}',
        '/inventory/inventory_item_custom/table',
    ],
    'inventory_item_add_inventory_item' => [
        '/inventory/inventory_item/add',
        '/inventory/inventory_item_stock/add',
        'inventory/inventory_item/add-custom'
    ],
    'inventory_item_edit_inventory_item' => [
        '/inventory/inventory_item/editar/{id}',
        '/inventory/inventory_item/update/{id}',
        '/inventory/inventory_item_stock/editar/{id}',
        '/inventory/inventory_item_stock/update/{id}',
        '/inventory/inventory_item_stock/upload_media',
        '/inventory/inventory_item_stock/delete_media/{id}',
        '/inventory/store_zone/search',
        '/inventory/store_zone/get-by-id/{id}',
        '/inventory/store_zone/update-zone'

    ],
    'inventory_item_delete_inventory_item' => [
        '/inventory/inventory_item/destroy/{id}',
        '/inventory/inventory_item_stock/destroy/{id}'
    ],

    'inventory_item_type_view_inventory_item_type' => [
        '/inventory/inventory_item_type',
        '/inventory/inventory_item_type/table'
    ],

    'inventory_item_type_add_inventory_item_type' => [
        '/inventory/inventory_item_type/add'
    ],
    'inventory_item_type_edit_inventory_item_type' => [
        '/inventory/inventory_item_type/editar/{id}',
        '/inventory/inventory_item_type/update/{id}'
    ],
    'inventory_item_type_delete_inventory_item_type' => [
        '/inventory/inventory_item_type/destroy/{id}'
    ],

    'inventory_movement_view_inventory_movement' => [
        '/inventory/inventory_movement',
        '/inventory/inventory_movement/table'

    ],
    'inventory_movement_add_inventory_movement' => [
        '/inventory/inventory_movement/add'
    ],
    'inventory_movement_edit_inventory_movement' => [
        '/inventory/inventory_movement/editar/{id}',
        '/inventory/inventory_movement/update/{id}'
    ],
    'inventory_movement_delete_inventory_movement' => [
        '/inventory/inventory_movement/destroy/{id}'
    ],


    'inventory_store_view_inventory_store' => ['/inventory/inventory_store', '/inventory/inventory_store/table'],
    'inventory_store_add_inventory_store' => ['/inventory/inventory_store/add'],
    'inventory_store_edit_inventory_store' => ['/inventory/inventory_store/editar/{id}', '/inventory_store/update/{id}'],
    'inventory_store_delete_inventory_store' => ['/inventory/inventory_store/destroy/{id}'],

    //inventario
    'inventory_item_custom_model_view_inventory_item_custom_model' => [
        '/inventory/inventory_item_custom_model',
        '/inventory/inventory_item_custom_model/table',
    ],
    'inventory_item_custom_model_add_inventory_item_custom_model' => [
        '/inventory/inventory_item_custom_model/add'
    ],
    'inventory_item_custom_model_edit_inventory_item_custom_model' => [
        '/inventory/inventory_item_custom_model/editar/{id}',
        '/inventory/inventory_item_custom_model/update/{id}'
    ],
    'inventory_item_custom_model_delete_inventory_item_custom_model' => [
        '/inventory/inventory_item_custom_model/destroy/{id}'
    ],

    'supervision_store' => [
        '/inventory',
        '/inventory/inventory_store/get-all',
        '/inventory/store_zone/get-store-zones-by-store/{id}',
        '/inventory/inventory_item_stock/change_stock',
        '/inventory/inventory_item/add_movement',
        '/inventory/inventory_item',
        '/inventory/inventory_item/table',
        '/inventory/inventory_item_stock',
        '/inventory/inventory_item_stock/table',
        '/inventory/inventory_item/add',
        '/inventory/inventory_item_stock/add',
        '/inventory/inventory_item/editar/{id}',
        '/inventory/inventory_item/update/{id}',
        '/inventory/inventory_item_stock/editar/{id}',
        '/inventory/inventory_item_stock/update/{id}',
        '/inventory/inventory_item/destroy/{id}',
        '/inventory/inventory_item_stock/destroy/{id}',
        '/inventory/inventory_item_type',
        '/inventory/inventory_item_type/table',
        '/inventory/inventory_item_type/add',
        '/inventory/inventory_item_type/editar/{id}',
        '/inventory/inventory_item_type/update/{id}',
        '/inventory/inventory_item_type/destroy/{id}',
        '/inventory/inventory_movement',
        '/inventory/inventory_movement/table',
        '/inventory/inventory_movement/add',
        '/inventory/inventory_movement/editar/{id}',
        '/inventory/inventory_movement/update/{id}',
        '/inventory/inventory_movement/destroy/{id}',
        '/inventory/inventory_store/my-store/{id}',
        '/inventory/inventory_item_stock/get_media_by_item/{id}',
        '/inventory/inventory_item_stock/upload_media',
        '/inventory/inventory_item_stock/delete_media/{id}',
        '/inventory/store_zone/search',
        '/inventory/store_zone/get-by-id/{id}',
        '/inventory/store_zone/update-zone',
        '/inventory/inventory_item_custom_model',
        '/inventory/inventory_item_custom_model/table',
        '/inventory/inventory_item_custom_model/add',
        '/inventory/inventory_item_custom_model/editar/{id}',
        '/inventory/inventory_item_custom_model/update/{id}',
        '/inventory/inventory_item_custom_model/destroy/{id}',
        '/inventory/inventory_item/add-custom',
        '/inventory/supplier',
        '/inventory/supplier/table',
        '/inventory/supplier/show/{id}',
        '/inventory/supplier/get-all',
        '/inventory/supplier/get-by-id/{id}',
        '/inventory/supplier-invoice',
        '/inventory/supplier-invoice/table',
        '/inventory/supplier-invoice/show/{id}',
        '/inventory/supplier-invoice/receive/{id}',
        '/inventory/supplier/{supplierId}/vendors',
        '/inventory/supplier/{supplierId}/vendors/get-all',
        '/inventory/supplier/{supplierId}/product-prices',
        '/inventory/supplier/product-prices/get-price-for-item',
        '/inventory/supplier/{supplierId}/table'

    ],

    // Suppliers
    'inventory_supplier_view_supplier' => [
        '/inventory/supplier',
        '/inventory/supplier/show/{id}',
        '/inventory/supplier/get-all',
        '/inventory/supplier/get-by-id/{id}',
        '/inventory/supplier/table',
        '/inventory/supplier/{supplierId}/vendors',
        '/inventory/supplier/{supplierId}/vendors/get-all',
        '/inventory/supplier/{supplierId}/product-prices',
        '/inventory/supplier/product-prices/get-price-for-item',
    ],
    'inventory_supplier_add_supplier' => [
        '/inventory/supplier/add',
        '/inventory/supplier/{supplierId}/vendors/add',
        '/inventory/supplier/{supplierId}/product-prices/add',
    ],
    'inventory_supplier_edit_supplier' => [
        '/inventory/supplier/update/{id}',
        '/inventory/supplier/{supplierId}/vendors/update/{vendorId}',
        '/inventory/supplier/{supplierId}/product-prices/update/{priceId}',
    ],
    'inventory_supplier_delete_supplier' => [
        '/inventory/supplier/destroy/{id}',
        '/inventory/supplier/{supplierId}/vendors/destroy/{vendorId}',
        '/inventory/supplier/{supplierId}/product-prices/destroy/{priceId}',
    ],

    // Supplier Invoices
    'inventory_supplier_invoice_view_supplier_invoice' => [
        '/inventory/supplier-invoice',
        '/inventory/supplier-invoice/create',
        '/inventory/supplier-invoice/show/{id}',
        '/inventory/supplier-invoice/table',
        '/inventory/supplier-invoice/receive/{id}',
    ],
    'inventory_supplier_invoice_add_supplier_invoice' => [
        '/inventory/supplier-invoice/add',
    ],
    'inventory_supplier_invoice_edit_supplier_invoice' => [
        '/inventory/supplier-invoice/update/{id}',
        '/inventory/supplier-invoice/receive/{id}',
    ],
    'inventory_supplier_invoice_delete_supplier_invoice' => [
        '/inventory/supplier-invoice/destroy/{id}',
    ],

    // Supplier Vendors
    'inventory_supplier_vendors_view_supplier_vendors' => [
        '/inventory/supplier/{supplierId}/vendors',
        '/inventory/supplier/{supplierId}/vendors/create',
        '/inventory/supplier/{supplierId}/vendors/get-all',
        '/inventory/supplier/{supplierId}/table'
    ],
    'inventory_supplier_vendors_add_supplier_vendors' => [
        '/inventory/supplier/{supplierId}/vendors/add',
    ],
    'inventory_supplier_vendors_edit_supplier_vendors' => [
        '/inventory/supplier/{supplierId}/vendors/update/{vendorId}',
    ],
    'inventory_supplier_vendors_delete_supplier_vendors' => [
        '/inventory/supplier/{supplierId}/vendors/destroy/{vendorId}',
    ],

    // Inventory Valuation
    'inventory_valuation_view_inventory_valuation' => [
        '/inventory/inventory-valuation',
        '/inventory/inventory-valuation/data',
    ],

    // Data Plan Promotions
    'view_data_plan_promotion' => [
        '/configuracion/data-plan-promotions',
        '/configuracion/data-plan-promotions/data',
    ],
    'add_data_plan_promotion' => [
        '/configuracion/data-plan-promotions/store',
    ],
    'edit_data_plan_promotion' => [
        '/configuracion/data-plan-promotions/update/{id}',
    ],
    'delete_data_plan_promotion' => [
        '/configuracion/data-plan-promotions/destroy/{id}',
    ],

    //Metodo de Pago
    'method_payment_view_method_payment' => [
        '/administracion/metotdo-de-pago',
        '/administracion/metotdo-de-pago/table',
    ],
    'method_payment_add_method_payment' => [
        '/administracion/metotdo-de-pago/add',
    ],
    'method_payment_edit_method_payment' => [
        '/administracion/metotdo-de-pago/editar/{id}',
        '/administracion/metotdo-de-pago/update/{id}',
    ],
    'method_payment_delete_method_payment' => [
        '/administracion/metotdo-de-pago/destroy/{id}',
    ],

    //Ift
    'ift_view_ift' => [
        '/administracion/ift/table',
        '/administracion/ift',
    ],
    'ift_edit_ift' => [
        '/administracion/ift/editar/{id}',
        '/administracion/ift/update/{id}'
    ],
    'ift_add_ift' => [
        '/administracion/ift/add'
    ],
    'ift_delete_ift' => [
        '/administracion/ift/destroy/{id}'
    ],

    //views
    'admin_view_meganet' => [
        ///Socios
        '/administracion/socios',
        '/administracion/socios/add',
        '/administracion/socios/editar/{id}',
        '/administracion/socios/update/{id}',
        '/administracion/socios/destroy/{id}',
        '/administracion/socios/table',
        ///Ubicaciones
        '/administracion/ubicacion',
        '/administracion/ubicacion/add',
        '/administracion/ubicacion/editar/{id}',
        '/administracion/ubicacion/update/{id}',
        '/administracion/ubicacion/destroy/{id}',
        '/administracion/ubicacion/table',

    ],

    //Registros
    'admin_view_registers' => [],

    //Informacion
    'admin_view_information' => [
        //Logs
        '/administracion/activity_log',
        '/administracion/activity_log/table'
    ],

    'admin_view_scripts' => [
        '/administracion/show_scripts'
    ],

    //Roles
    'role_view_role' => [
        '/administracion/rol',
        '/administracion/rol/table',
        '/administracion/rol/get-all',
    ],
    'role_add_role' => [
        '/administracion/rol/add',
    ],
    'role_edit_role' => [
        '/administracion/rol/editar-role/{id}',
        '/administracion/rol/update-role/{id}',
    ],
    'role_delete_role' => [
        '/administracion/rol/destroy/{id}',
    ],
    'role_permission_role' => [
        //visual
    ],

    //Usuarios
    'user_view_user' => [
        '/administracion/user',
        '/administracion/user/table',
        '/administracion/user/get-all-users',
    ],
    'user_add_user' => [
        '/administracion/user/add',
    ],
    'user_edit_user' => [
        '/administracion/user/editar/{id}',
        '/administracion/user/update/{id}',
        '/administracion/user/get-data-user/{id}'
    ],
    'user_delete_user' => [
        '/administracion/user/destroy/{id}',
    ],
    'user_permision_user' => [
        //visual
    ],

    // Documentation
    'documentation_view_documentation' => [
        '/administracion/documentation/documentation_menu/',
        '/administracion/documentation/documentation_menu/getById/{id}',
        '/administracion/documentation/documentation_menu/get-title/{id}',
        '/administracion/documentation/documentation_submenu/',
        '/administracion/documentation/documentation_submenu/{id}',
        '/administracion/documentation/documentation_content/{submenu_id}/contents',
    ],
    'documentation_add_documentation' => [
        '/administracion/documentation/documentation_menu/add',
        '/administracion/documentation/documentation_submenu/add',
        '/administracion/documentation/documentation_content/add',
    ],
    'documentation_edit_documentation' => [
        '/administracion/documentation/documentation_menu/update/{id}',
        '/administracion/documentation/documentation_submenu/update/{id}',
        '/administracion/documentation/documentation_content/update/{id}',
    ],
    'documentation_delete_documentation' => [
        '/administracion/documentation/documentation_menu/destroy/{id}',
        '/administracion/documentation/documentation_submenu/destroy/{id}',
        '/administracion/documentation/documentation_content/delete/{id}',
    ],    
    

    //Sucursales
    'view_sucursal' => [
        '/administracion/sucursal',
        '/administracion/sucursal/table',
    ],
    'add_sucursal' => [
        '/administracion/sucursal/add',
    ],
    'edit_sucursal' => [
        '/administracion/sucursal/editar-role/{id}',
        '/administracion/sucursal/update-role/{id}',
    ],
    'delete_sucursal' => [
        '/administracion/sucursal/destroy/{id}',
    ],

    //Module Manager (/admin/modules)
    'admin_modules' => [
        '/admin/modules',
        '/admin/modules/{slug}/history',
    ],
    'admin_modules_migrate' => [
        '/admin/modules/{slug}/migrate',
    ],
    'admin_modules_toggle' => [
        '/admin/modules/{slug}/toggle',
    ],

    //Configuración
    'config_view_module' => ['/configuracion'],
    'config_view_system' => [
        //Campos adicionales
        '/configuracion/additional-fields',
        '/configuracion/additional-fields/add',
        '/configuracion/additional-fields/editar/{id}',
        '/configuracion/additional-fields/update/{id}',
        '/configuracion/additional-fields/destroy/{id}',
        '/configuracion/additional-fields/table',
        '/configuracion/additional-fields/get-required-value/{id}',
        '/configuracion/additional-fields/update-position-field',

        //Plantillas
        '/configuracion/list-template-verification',
        '/configuracion/list-template-verification/add',
        '/configuracion/list-template-verification/editar/{id}',
        '/configuracion/list-template-verification/update/{id}',
        '/configuracion/list-template-verification/destroy/{id}',
        '/configuracion/list-template-verification/table',
        '/configuracion/list-template-verification/get-check-list-template/{id}',

        '/configuracion/template-task',
        '/configuracion/template-task/add',
        '/configuracion/template-task/editar/{id}',
        '/configuracion/template-task/update/{id}',
        '/configuracion/template-task/destroy/{id}',
        '/configuracion/template-task/table',

        '/administracion/document_template',
        '/administracion/document_template/table',
        '/administracion/document_template/load_content_template',
        '/administracion/document_template/show_content_template',
        '/administracion/document_template/get_variables',
        '/administracion/document_template/add',
        '/administracion/document_template/update/{id}',
        '/administracion/document_template/destroy/{id}',
        '/administracion/document_template/get_data_template/{id}',
        '/administracion/document_type_template',
        '/administracion/document_type_template/add',
        '/administracion/document_type_template/editar/{id}',
        '/administracion/document_type_template/update/{id}',
        '/administracion/document_type_template/destroy/{id}',
        '/administracion/document_type_template/table',
        //
    ],

    //Principal
    'config_view_main' => [],
    //Finanzas
    'config_view_finance' => [
        //Metodos de Pago
        '/configuracion/metodos-de-pago',
        '/configuracion/metodos-de-pago/get-all-methods',
        '/configuracion/metodos-de-pago/{id}/edit',
        '/configuracion/metodos-de-pago/create',
        '/configuracion/metodos-de-pago/{id}/update',
        '/configuracion/metodos-de-pago/{id}/destroy',

        ///Pago de Deudas Clientes Custom
        '/configuracion/debt-payment-client-recurrent',
        '/configuracion/debt-payment-client-custom',
        '/configuracion/debitcustom',
        '/configuracion/debitcustom/add',
        '/configuracion/debitcustom/editar/{id}',
        '/configuracion/debitcustom/update/{id}',
        '/configuracion/debitcustom/destroy/{id}',
        '/configuracion/debitcustom/table',



    ],

    'config_view_finance_notification' => [
        '/configuracion/finance-notification',
        '/configuracion/finance-notification/get-data-tabs',
        '/configuracion/finance-notification/update/{id}',

    ],

    //Red
    'config_view_network_management' => [
        '/configuracion/nomenclature',
        '/configuracion/nomenclature/table',
        '/configuracion/nomenclature/add',
        '/configuracion/nomenclature/editar/{id}',
        '/configuracion/nomenclature/update/{id}',
        '/configuracion/nomenclature/destroy/{id}',
        '/configuracion/nomenclature/assign_client/{id}',
        '/configuracion/nomenclature/get-nomenclature-by-client/{id}',
        '/configuracion/nomenclature/add-multiple-nomenclatures',
    ],
    'service_in_address_list_view_service_in_address_list' => [
        '/configuracion/service_in_address_list'
    ],

    'nomenclature_view_nomenclature' => [
        '/configuracion/nomenclature',
        '/configuracion/nomenclature/table',
        '/configuracion/nomenclature/get-nomenclature-by-client/{id}',
    ],
    'nomenclature_add_nomenclature' => [
        '/configuracion/nomenclature/add',
        '/configuracion/nomenclature/add-multiple-nomenclatures',
    ],
    'nomenclature_edit_nomenclature' => [
        '/configuracion/nomenclature/editar/{id}',
        '/configuracion/nomenclature/update/{id}',
        '/configuracion/nomenclature/get-nomenclature-by-client/{id}',
        '/configuracion/nomenclature/add-multiple-nomenclatures',
    ],
    'nomenclature_delete_nomenclature' => [
        '/configuracion/nomenclature/destroy/{id}',
    ],
    //helpdesk
    'config_view_helpdesk' => [],
    //Actividades Programadas
    'config_view_scheduling' => [
        //flujo de trabajo
        '/configuracion/work-flow',
        '/configuracion/work-flow/add',
        '/configuracion/work-flow/editar/{id}',
        '/configuracion/work-flow/update/{id}',
        '/configuracion/work-flow/destroy/{id}',
        '/configuracion/work-flow/table',

        //Comandos
        '/configuracion/command',
        '/configuracion/command/update/{id}',

        //Lista de Plantillas de Verificacion
        '/configuracion/list-template-verification',
        '/configuracion/list-template-verification/add',
        '/configuracion/list-template-verification/editar/{id}',
        '/configuracion/list-template-verification/update/{id}',
        '/configuracion/list-template-verification/destroy/{id}',
        '/configuracion/list-template-verification/table',
        '/configuracion/list-template-verification/get-check-list-template/{id}',
    ],

    //Clientes Potenciales
    'config_view_potencial_customer' => [],
    //INventory
    'config_view_inventory' => [],
    //Intergrations
    'config_view_integrations' => [],
    //Voice
    'config_view_voice' => [],
    //Tools
    'config_view_tools' => [
        '/configuracion/tools-import',
        '/configuracion/tools-import/crear',
        '/configuracion/tools-import/create',
        '/configuracion/tools-import/{id}/editar',
        '/configuracion/tools-import/{id}/update',
        '/configuracion/tools-import/destroy/{id}',
        '/configuracion/tools-import/table',
        '/configuracion/tools-import/get-example-for-this-module',
    ],

    'config_view_sales' => [
        '/configuracion/credencial/modificar-credencial',
        '/configuracion/credencial/image-front',
        '/configuracion/credencial/image-back',
        '/configuracion/credencial/image-logo',
        '/configuracion/credencial/upload',
        '/configuracion/medios-de-venta',
        '/configuracion/medios-de-venta/get-mediums-sales',
        '/configuracion/medios-de-venta/{id}/get-by-id',
        '/configuracion/medios-de-venta/create',
        '/configuracion/medios-de-venta/{id}/update',
        '/configuracion/medios-de-venta/{id}/destroy',
        '/configuracion/comisiones/get-types-sellers',
        '/configuracion/comisiones/{id}/get-commissions-pending',
        '/configuracion/comisiones/{id}/get-by-type-seller',
        '/configuracion/comisiones/{id}/get-total-amount-commission',
        '/configuracion/comisiones/{id}/get-commissions-by-seller',
        '/configuracion/comisiones/get-list-commissions-by-seller/{seller_id}',
        '/configuracion/comisiones/{id}/get-details-commission',
        '/configuracion/comisiones/create-comision-internal-distributor',
        '/configuracion/rules',
        '/configuracion/rules/get-sellers-by-type/{type}',
        '/configuracion/rules/create',
        '/configuracion/rules/store',
        '/configuracion/rules/edit/{id}',
        '/configuracion/rules/update/{id}',
        '/configuracion/rules/destroy/{id}',
        '/configuracion/rules/table',
        '/configuracion/reglas-comisiones',
        '/configuracion/reglas-comisiones/editar/{id_rule}',
        '/configuracion/reglas-comisiones/vendedores/{id_rule}',
        '/configuracion/reglas-comisiones/get-all-rules',
        '/configuracion/reglas-comisiones/get-sellers-by-type/{id_type}',
        '/configuracion/reglas-comisiones/get-sellers/{id_rule}',
        '/configuracion/reglas-comisiones/crear',
        '/configuracion/reglas-comisiones/get-rule-by-id/{id_rule}',
        '/configuracion/reglas-comisiones/get-rule-by-seller/{seller_id}',
        '/configuracion/reglas-comisiones/create',
        '/configuracion/reglas-comisiones/update/{id_rule}',
        '/configuracion/reglas-comisiones/delete/{id_rule}',
        '/configuracion/tipos-vendedores',
        '/configuracion/tipos-vendedores/get-all-types',
        '/configuracion/tipos-vendedores/{id}/edit',
        '/configuracion/tipos-vendedores/create',
        '/configuracion/tipos-vendedores/{id}/update',
        '/configuracion/tipos-vendedores/{id}/destroy',
        '/configuracion/estados-vendedores',
        '/configuracion/estados-vendedores/get-all-status',
        '/configuracion/estados-vendedores/{id}/edit',
        '/configuracion/estados-vendedores/create',
        '/configuracion/estados-vendedores/{id}/update',
        '/configuracion/estados-vendedores/{id}/destroy',
        '/configuracion/metodos-de-pago',
        '/configuracion/metodos-de-pago/get-all-methods',
        '/configuracion/metodos-de-pago/{id}/edit',
        '/configuracion/metodos-de-pago/create',
        '/configuracion/metodos-de-pago/{id}/update',
        '/configuracion/metodos-de-pago/{id}/destroy',
        '/configuracion/rangos-venta',
        '/configuracion/rangos-venta/get-all-ranges-sales',
        '/configuracion/rangos-venta/sector-one',
        '/configuracion/rangos-venta/sector-two',
        '/configuracion/rangos-venta/sector-three',
        '/configuracion/rangos-venta/{id}/edit',
        '/configuracion/rangos-venta/{id}/update',
    ],

    'company_information_view_company_information' => [
        '/configuracion/company-information',
        '/configuracion/company-information/get-data-company',
    ],
    'company_information_add_company_information' => [
        '/configuracion/company-information/add',
    ],
    'company_information_edit_company_information' => [
        '/configuracion/company-information/editar/{id}',
        '/configuracion/company-information/update/{id}',
    ],
    'company_information_delete_company_information' => [
        '/configuracion/company-information/destroy/{id}',
    ],


    //Google Maps
    'config_view_google_maps' => [
        '/configuracion/credenciales-google-maps',
        '/configuracion/credenciales-google-maps/edit',
        '/configuracion/credenciales-google-maps/create',
        '/configuracion/credenciales-google-maps/{id}/update',
        '/configuracion/credenciales-google-maps/{id}/destroy',
    ],

    'recurring_debts_payments_custom_view' => ['/configuracion/debitcustom', '/configuracion/debitcustom/table'],
    'recurring_debts_payments_custom_add' => ['/configuracion/debitcustom/add'],
    'recurring_debts_payments_custom_edit' => ['/configuracion/debitcustom/editar/{id}', '/configuracion/debitcustom/update/{id}'],
    'recurring_debts_payments_custom_destroy' => ['/configuracion/debitcustom/destroy/{id}'],


    'inbox_view_inbox' => [
        '/message/inbox',
        '/message/get-data-tabs',
        '/message/reminder/table',
        '/message/payment_email/table',
        '/message/invoice_email/table'
    ],
    'inbox_send_message' => [
        '/message/reminder/send_message',
        '/message/payment_email/send_message',
        '/message/invoice_email/send_message'
    ],

    // Smart Import/Export (addon-smart-import-export)
    'smart_import_view' => [
        '/configuracion/smart-import',
        '/configuracion/smart-import-export',
        '/configuracion/smart-import-export/history',
        '/configuracion/smart-import-export/log/{id}',
        '/configuracion/smart-import-export/log/{id}/download',
    ],
    'smart_import_execute' => [
        '/configuracion/smart-import/upload',
        '/configuracion/smart-import/preview',
        '/configuracion/smart-import/execute',
        '/configuracion/smart-import/status/{jobId}',
    ],
    'smart_export_view' => [
        '/configuracion/smart-export',
        '/configuracion/smart-export/modules',
        '/configuracion/smart-import-export',
        '/configuracion/smart-import-export/history',
        '/configuracion/smart-import-export/log/{id}',
        '/configuracion/smart-import-export/log/{id}/download',
    ],
    'smart_export_execute' => [
        '/configuracion/smart-export/generate',
        '/configuracion/smart-export/download/{token}',
    ],

    // Manual de Usuario (addon-manual)
    'manual_view' => [
        '/manual',
        '/api/manual/sections',
        '/api/manual/sections/{slug}',
    ],
    'manual_generate' => [
        '/api/manual/generate',
    ],

    // Pagos SPEI / OpenPay
    'payments_manage_providers' => [
        '/finanzas/metodos-pago',
        '/finanzas/payment-providers',
        '/finanzas/payment-providers/{id}',
    ],
    'payments_assign_clabe' => [
        '/finanzas/clients/{id}/clabe',
        '/finanzas/clients/{id}/assign-clabe',
    ],
    'payments_view_receipts' => [
        '/finanzas/payments/{id}/receipt',
        '/finanzas/payments/{payment}/receipt/{receipt}/download',
    ],

    // ══════════════════════════════════════════════════════════════════════════
    // DASHBOARD — fix acceso post-login para usuarios no-admin
    // ══════════════════════════════════════════════════════════════════════════
    // (reemplaza los [] vacíos — necesitamos patch AQUÍ porque la clave ya existe arriba;
    //  en el Paso C se corrige la clave original)

    // ══════════════════════════════════════════════════════════════════════════
    // VoIP (addon-voip)
    // ══════════════════════════════════════════════════════════════════════════
    'voip.view' => [
        '/voip',
        '/voip/troncales',
        '/voip/troncales/data',
        '/voip/probar-conexion',
    ],
    'voip.troncales.view' => [
        '/voip/troncales',
        '/voip/troncales/data',
        '/voip/troncales/{troncal}/verificar',
        '/voip/probar-conexion',
    ],
    'voip.troncales.manage' => [
        '/voip/troncales',
        '/voip/troncales/{troncal}',
        '/voip/troncales/{troncal}/provisionar',
        '/voip/troncales/{troncal}/desprovisionar',
        '/voip/troncales/{troncal}/verificar',
        '/voip/probar-conexion',
    ],
    'voip.extensiones.view' => [
        '/voip/extensiones',
        '/voip/extensiones/data',
        '/voip/extensiones/usuarios',
        '/voip/extensiones/{extension}/verificar',
    ],
    'voip.extensiones.manage' => [
        '/voip/extensiones',
        '/voip/extensiones/{extension}',
        '/voip/extensiones/{extension}/provisionar',
        '/voip/extensiones/{extension}/desprovisionar',
        '/voip/extensiones/{extension}/verificar',
    ],
    'voip.grupos.view' => [
        '/voip/grupos-timbrado',
        '/voip/grupos-timbrado/data',
        '/voip/grupos-timbrado/extensiones-disponibles',
    ],
    'voip.grupos.manage' => [
        '/voip/grupos-timbrado',
        '/voip/grupos-timbrado/{grupoTimbrado}',
    ],
    'voip.ia-bot.view' => [
        '/voip/ia-bot',
        '/voip/ia-bot/config',
        '/voip/ia-bot/conversaciones',
        '/voip/ia-bot/leads',
        '/voip/ia-bot/kb',
    ],
    'voip.ia-bot.manage' => [
        '/voip/ia-bot/config',
        '/voip/ia-bot/leads/{lead}',
        '/voip/ia-bot/kb',
        '/voip/ia-bot/kb/{kb}',
    ],

    // ══════════════════════════════════════════════════════════════════════════
    // Flotas (addon-flotas)
    // ══════════════════════════════════════════════════════════════════════════
    'fleet.view' => [
        '/flotas',
        '/flotas/nuevo',
        '/flotas/{id}',
        '/flotas/api/**',           // todos los GET de API de flotas
    ],
    'fleet.gps.view' => [
        '/flotas/mapa',
        '/flotas/api/gps/flota',
        '/flotas/api/vehiculos/{id}/gps',
        '/flotas/api/vehiculos/{id}/gps/history',
        '/flotas/api/vehiculos/{id}/gps/device',
        '/flotas/api/vehiculos/{id}/geofence-events',
        '/flotas/api/notificaciones-log',
        '/flotas/api/vehiculos/{id}/notifications/preference',
    ],
    'fleet.gps.manage' => [
        '/flotas/api/vehiculos/{id}/gps/device',
        '/flotas/api/vehiculos/{id}/notifications/preference',
        '/flotas/api/notificaciones-log/{id}/resend',
    ],
    'fleet.geofences.view' => [
        '/flotas/geocercas',
        '/flotas/geocercas/nueva',
        '/flotas/geocercas/{id}',
        '/flotas/geocercas/{id}/editar',
        '/flotas/api/geocercas',
        '/flotas/api/geocercas/{id}',
    ],
    'fleet.geofences.manage' => [
        '/flotas/geocercas/nueva',
        '/flotas/geocercas/{id}/editar',
        '/flotas/api/geocercas',
        '/flotas/api/geocercas/{id}',
        '/flotas/api/geocercas/{id}/vehiculos',
    ],
    'fleet.notifications.view' => [
        '/flotas/notificaciones-log',
        '/flotas/api/notificaciones-log',
    ],
    'fleet.rules.view' => [
        '/flotas/reglas',
        '/flotas/api/reglas',
    ],
    'fleet.rules.manage' => [
        '/flotas/api/reglas',
        '/flotas/api/reglas/{id}',
        '/flotas/api/reglas/{id}/toggle',
    ],
    'fleet.documents.view' => [
        '/flotas/api/documentos',
        '/flotas/api/documentos/alertas/proximos',
        '/flotas/api/documentos/dashboard/all',
        '/flotas/api/documentos/{id}',
        '/flotas/api/documentos/{id}/descargar',
        '/flotas/api/documentos/{id}/renovar/prefill',
    ],
    'fleet.documents.manage' => [
        '/flotas/api/documentos',
        '/flotas/api/documentos/{id}',
    ],
    'fleet.maintenance.manage' => [
        '/flotas/api/mantenimientos',
        '/flotas/api/mantenimientos/{id}',
        '/flotas/api/mantenimientos/{id}/files',
        '/flotas/api/proveedores',
        '/flotas/api/proveedores/{id}',
    ],
    'fleet.fuel.manage' => [
        '/flotas/api/combustible',
        '/flotas/api/combustible/{id}',
    ],
    'fleet.providers.manage' => [
        '/flotas/api/proveedores',
        '/flotas/api/proveedores/{id}',
    ],
    'fleet.assign' => [
        '/flotas/api/vehiculos/{id}/asignacion',
    ],

    // ══════════════════════════════════════════════════════════════════════════
    // WarRoom (addon-warroom)
    // ══════════════════════════════════════════════════════════════════════════
    'warroom.view' => [
        '/warroom',
        '/warroom/api/kpis/**',
        '/warroom/api/desempeno',
        '/warroom/api/desempeno/serie',
        '/warroom/api/insights/**',
        '/warroom/api/meetings',
        '/warroom/api/meetings/active',
        '/warroom/api/meetings/{meeting}/detalle',
        '/warroom/api/meetings/{meeting}/notes',
        '/warroom/api/action-items',
    ],
    'warroom.manage' => [
        '/warroom/api/action-items',
        '/warroom/api/action-items/{actionItem}',
        '/warroom/api/meetings/start',
        '/warroom/api/meetings/{meeting}/end',
        '/warroom/api/meetings/{meeting}/pause',
        '/warroom/api/meetings/{meeting}/resume',
        '/warroom/api/meetings/{meeting}/notes',
        '/warroom/api/insights/{view}/{period}/regenerate',
    ],

    // ══════════════════════════════════════════════════════════════════════════
    // Embajadores (addon-embajadores)
    // ══════════════════════════════════════════════════════════════════════════
    'embajadores.view' => [
        '/embajadores',
        '/embajadores/dashboard/summary',
        '/embajadores/metrics',
        '/embajadores/metrics/data',
        '/embajadores/clientes',
        '/embajadores/clientes/data',
        '/embajadores/clientes/{id}',
        '/embajadores/clientes/{id}/tree',
        '/embajadores/comisiones',
        '/embajadores/comisiones/data',
        '/embajadores/tiers',
        '/embajadores/tiers/data',
        '/embajadores/configuracion',
        '/embajadores/configuracion/get',
        '/embajadores/video',
    ],
    'embajadores.commissions.approve' => [
        '/embajadores/comisiones/{id}/approve',
    ],
    'embajadores.commissions.cancel' => [
        '/embajadores/comisiones/{id}/cancel',
    ],
    'embajadores.tiers.manage' => [
        '/embajadores/tiers',
        '/embajadores/tiers/{id}',
        '/embajadores/tiers/{id}/toggle',
    ],
    'embajadores.configure' => [
        '/embajadores/configuracion',
        '/embajadores/configuracion/get',
        '/embajadores/configuracion/update',
    ],
    'embajadores.payouts.generate' => [
        '/embajadores/comisiones/**',
    ],
    'embajadores.rewards.manage' => [
        '/embajadores/**',
    ],

    // ══════════════════════════════════════════════════════════════════════════
    // Cobranza Blaster (addon-cobranza)
    // ══════════════════════════════════════════════════════════════════════════
    'cobranza.view' => [
        '/cobranza',
        '/cobranza/campanas',
        '/cobranza/campanas/data',
        '/cobranza/campanas/kpis',
        '/cobranza/campanas/{id}/llamadas',
        '/cobranza/voip',
        '/cobranza/voip/configuracion',
        '/cobranza/voip/test',
    ],
    'cobranza.manage' => [
        '/cobranza/campanas',
        '/cobranza/campanas/{id}',
        '/cobranza/campanas/{id}/activar',
        '/cobranza/campanas/{id}/pausar',
    ],
    'cobranza.configure' => [
        '/cobranza/voip',
        '/cobranza/voip/configuracion',
        '/cobranza/voip/test',
    ],

    // ══════════════════════════════════════════════════════════════════════════
    // Talento (addon-talento) — mapeo granular por permiso
    // ══════════════════════════════════════════════════════════════════════════
    // Gate de módulo (sidebar + página principal)
    'talento.view' => [
        '/talento',
        '/talento/dashboard',
    ],
    'talento.dashboard.view' => [
        '/talento',
        '/talento/dashboard',
        '/talento/api/cajas',
        '/talento/api/cajas/latest',
        '/talento/api/cajas/bonus-log',
        '/talento/api/cajas/settings',
        '/talento/api/work-order-types',
        '/talento/api/app/branding',
        '/talento/api/app/latest',
    ],
    'talento.employees.view' => [
        '/talento/api/colaboradores',
        '/talento/api/colaboradores/role-departments',
        '/talento/api/colaboradores/users-disponibles',
        '/talento/api/colaboradores/{id}',
        '/talento/api/colaboradores/{id}/academy-progress',
        '/talento/api/colaboradores/{id}/avance',
        '/talento/api/colaboradores/{id}/certifications',
        '/talento/api/colaboradores/{id}/credentials',
        '/talento/api/colaboradores/{id}/custodia',
        '/talento/api/colaboradores/{id}/dispositivos',
        '/talento/api/colaboradores/{id}/embajador-data',
        '/talento/api/colaboradores/{id}/funds',
        '/talento/api/colaboradores/{id}/level-eligibility',
        '/talento/api/colaboradores/{id}/level-history',
        '/talento/api/colaboradores/{id}/loans',
        '/talento/api/colaboradores/{id}/proyecto-avance',
        '/talento/api/colaboradores/{id}/regla',
        '/talento/api/colaboradores/{id}/regla/historial',
        '/talento/api/colaboradores/{id}/seller-data',
    ],
    'talento.manage' => [
        '/talento/api/colaboradores',
        '/talento/api/colaboradores/{id}',
        '/talento/api/colaboradores/{id}/regla',
    ],
    'talento.attendance.view' => [
        '/talento/asistencia',
        '/talento/api/asistencia',
        '/talento/api/asistencia/hoy',
        '/talento/api/asistencia/{id}',
        '/talento/mapa-en-vivo',
        '/talento/api/ubicacion/en-vivo',
        '/talento/api/ubicacion/{colaboradorId}/ruta',
    ],
    'talento.attendance.manage' => [
        '/talento/api/asistencia/check-in',
        '/talento/api/asistencia/check-out',
        '/talento/api/asistencia/checkin',
        '/talento/api/asistencia/checkout',
        '/talento/api/asistencia/ping',
        '/talento/api/asistencia/{id}',
        '/talento/api/asistencia/{id}/extension',
    ],
    'talento.attendance.checkin' => [
        '/talento/api/asistencia/checkin',
        '/talento/api/asistencia/checkout',
        '/talento/api/asistencia/ping',
    ],
    'talento.orders.view' => [
        '/talento/ordenes',
        '/talento/campo',
        '/talento/api/ots/**',
        '/talento/api/campo/{workOrderId}/estado',
        '/talento/api/campo/{workOrderId}/firmas',
        '/talento/api/campo/{workOrderId}/media',
        '/talento/api/campo/{workOrderId}/activacion',
        '/talento/api/campo/{workOrderId}/encuesta',
        '/talento/api/campo/{workOrderId}/ia-validacion',
        '/talento/api/work-order-types',
        '/talento/api/standards',
        '/talento/api/sitios',
        '/talento/api/sitios/config/radio',
        '/talento/sitios',
    ],
    'talento.work_orders.view' => [
        '/talento/ordenes',
        '/talento/campo',
        '/talento/api/ots/**',
        '/talento/api/campo/{workOrderId}/estado',
        '/talento/api/campo/{workOrderId}/firmas',
        '/talento/api/campo/{workOrderId}/media',
        '/talento/api/campo/{workOrderId}/activacion',
        '/talento/api/campo/{workOrderId}/encuesta',
        '/talento/api/campo/{workOrderId}/ia-validacion',
    ],
    'talento.work_orders.manage' => [
        '/talento/api/ots/**',
        '/talento/api/campo/{workOrderId}/aceptar',
        '/talento/api/campo/{workOrderId}/activar',
        '/talento/api/campo/{workOrderId}/firma',
        '/talento/api/campo/{workOrderId}/media',
        '/talento/api/campo/{workOrderId}/encuesta',
        '/talento/api/campo/{workOrderId}/onboarding',
        '/talento/api/campo/{workOrderId}/ia-validacion',
        '/talento/api/standards',
        '/talento/api/standards/{id}',
        '/talento/api/standards/{id}/image',
    ],
    'talento.work_orders.validate' => [
        '/talento/api/ots/**',
    ],
    'talento.field_flow.accept' => [
        '/talento/api/campo/{workOrderId}/aceptar',
        '/talento/api/campo/{workOrderId}/activar',
        '/talento/api/campo/{workOrderId}/firma',
    ],
    'talento.ia_validation.view' => [
        '/talento/api/campo/{workOrderId}/ia-validacion',
    ],
    'talento.ia_validation.override' => [
        '/talento/api/campo/ia-validacion/{validationId}/override',
    ],
    'talento.compensations.view' => [
        '/talento/compensacion',
        '/talento/cajas',
        '/talento/api/compensacion/semana',
        '/talento/api/cajas/**',
    ],
    'talento.compensation.view' => [
        '/talento/compensacion',
        '/talento/cajas',
        '/talento/api/compensacion/semana',
        '/talento/api/cajas/**',
    ],
    'talento.compensation.manage' => [
        '/talento/api/cajas',
        '/talento/api/cajas/settings',
        '/talento/api/cajas/bonus-log/{workOrderId}/evaluate',
    ],
    'talento.liquidations.view' => [
        '/talento/liquidaciones',
        '/talento/finiquito',
        '/talento/api/settlements',
        '/talento/api/settlements/{id}',
    ],
    'talento.liquidation.view' => [
        '/talento/liquidaciones',
        '/talento/finiquito',
        '/talento/api/settlements',
        '/talento/api/settlements/{id}',
    ],
    'talento.liquidation.manage' => [
        '/talento/api/settlements/{id}/close',
        '/talento/api/colaboradores/{id}/settlement/draft',
        '/talento/api/settlement-items/{id}',
    ],
    'talento.settlement.view' => [
        '/talento/finiquito',
        '/talento/api/settlements',
        '/talento/api/settlements/{id}',
    ],
    'talento.settlement.manage' => [
        '/talento/api/settlements/{id}/close',
        '/talento/api/colaboradores/{id}/settlement/draft',
    ],
    'talento.catalog.view' => [
        '/talento/api/activity-types',
        '/talento/api/activity-types-config',
        '/talento/api/work-order-types',
    ],
    'talento.activity_types.view' => [
        '/talento/api/activity-types',
        '/talento/api/activity-types-config',
    ],
    'talento.activity_types.manage' => [
        '/talento/api/activity-types',
        '/talento/api/activity-types/{id}',
        '/talento/api/activity-types/{id}/level',
        '/talento/api/work-order-types/{id}/level',
    ],
    'talento.academy.view' => [
        '/talento/academia',
        '/talento/api/courses',
        '/talento/api/courses/{id}',
        '/talento/practical-evidence/{id}',
        '/talento/api/colaboradores/{id}/academy-progress',
        '/talento.api/colaboradores/{id}/certifications',
    ],
    'talento.academy.manage' => [
        '/talento/api/courses',
        '/talento/api/courses/{id}',
        '/talento/api/course-materials/{id}',
    ],
    'talento.academy.evaluate' => [
        '/talento/api/courses/{id}',
        '/talento/api/certifications/{id}/revoke',
    ],
    'talento.levels.view' => [
        '/talento/niveles',
        '/talento/escalafon',
        '/talento/api/colaboradores/{id}/level-eligibility',
        '/talento/api/colaboradores/{id}/level-history',
    ],
    'talento.levels.manage' => [
        '/talento/api/colaboradores/{id}/promote',
    ],
    'talento.escalafon.view' => [
        '/talento/escalafon',
    ],
    'talento.escalafon.manage' => [
        '/talento/escalafon',
    ],
    'talento.penalties.view' => [
        '/talento/penalizaciones',
        '/talento/api/penalties/**',
        '/talento/penalty-evidence/{id}',
    ],
    'talento.penalties.manage' => [
        '/talento/api/penalties/**',
    ],
    'talento.penalties.appeal' => [
        '/talento/api/penalties/{id}/appeal',
    ],
    'talento.penalties.resolve' => [
        '/talento/api/penalties/{id}/resolve',
        '/talento/api/penalties/{id}/dismiss',
    ],
    'talento.projects.view' => [
        '/talento/proyectos',
        '/talento/api/proyectos/**',
        '/talento/api/colaboradores/{id}/proyecto-avance',
    ],
    'talento.projects.manage' => [
        '/talento/api/proyectos/**',
    ],
    'talento.project_reports.view' => [
        '/talento/api/project-reports/**',
    ],
    'talento.project_reports.create' => [
        '/talento/api/project-reports/**',
    ],
    'talento.project_reports.approve' => [
        '/talento/api/project-reports/{id}/approve',
    ],
    'talento.quality.view' => [
        '/talento/calidad',
        '/talento/api/quality/**',
        '/talento/api/standards',
        '/talento/api/standards/{id}',
    ],
    'talento.quality.manage' => [
        '/talento/api/quality/**',
        '/talento/api/standards',
        '/talento/api/standards/{id}',
        '/talento/api/standards/{id}/image',
    ],
    'talento.routes.view' => [
        '/talento/rutas',
        '/talento/api/rutas',
        '/talento/api/rutas/{id}',
    ],
    'talento.routes.manage' => [
        '/talento/api/rutas',
        '/talento/api/rutas/{id}',
        '/talento/api/rutas/{id}/activar',
        '/talento/api/rutas/{id}/analizar-desvios',
        '/talento/api/rutas/{id}/stops/reordenar',
    ],
    'talento.roadmap.view' => [
        '/talento/roadmap',
        '/talento/api/roadmap/**',
    ],
    'talento.roadmap.manage' => [
        '/talento/api/roadmap/**',
    ],
    'talento.work_sites.view' => [
        '/talento/sitios',
        '/talento/api/sitios',
        '/talento/api/sitios/config/radio',
        '/talento/api/sitios/{id}',
    ],
    'talento.work_sites.manage' => [
        '/talento/api/sitios',
        '/talento/api/sitios/config/radio',
        '/talento/api/sitios/{id}',
    ],
    'talento.credentials.view' => [
        '/talento/credenciales',
        '/talento/credential-doc/{id}',
        '/talento/api/colaboradores/{id}/credentials',
    ],
    'talento.credentials.manage' => [
        '/talento/api/colaboradores/{id}/credentials',
    ],
    'talento.devices.view' => [
        '/talento/dispositivos',
        '/talento/api/colaboradores/{id}/dispositivos',
    ],
    'talento.devices.manage' => [
        '/talento/api/colaboradores/{id}/dispositivos',
        '/talento/api/colaboradores/{id}/dispositivos/{devId}',
        '/talento/api/colaboradores/{id}/dispositivos/{devId}/approve',
    ],
    'talento.custody.view' => [
        '/talento/custodia',
        '/talento/api/colaboradores/{id}/custodia',
    ],
    'talento.location.view' => [
        '/talento/mapa-en-vivo',
        '/talento/api/ubicacion/en-vivo',
        '/talento/api/ubicacion/{colaboradorId}/ruta',
    ],
    'talento.media.view' => [
        '/talento/media/{id}',
        '/talento/inspection-photo/{id}',
        '/talento/api/campo/{workOrderId}/media',
    ],
    'talento.media.upload' => [
        '/talento/api/campo/{workOrderId}/media',
    ],
    'talento.media.view_sensitive' => [
        '/talento/media/{id}',
        '/talento/inspection-photo/{id}',
        '/talento/penalty-evidence/{id}',
        '/talento/practical-evidence/{id}',
    ],
    'talento.signatures.view' => [
        '/talento/api/campo/{workOrderId}/firmas',
        '/talento/api/campo/{workOrderId}/firma',
    ],
    'talento.loans.view' => [
        '/talento/api/colaboradores/{id}/loans',
    ],
    'talento.loans.manage' => [
        '/talento/api/loans/**',
    ],
    'talento.funds.view' => [
        '/talento/api/colaboradores/{id}/funds',
    ],
    'talento.funds.manage' => [
        '/talento/api/funds/**',
    ],
    'talento.ledger.view' => [
        '/talento/api/ledger/**',
    ],
    'talento.caja.view' => [
        '/talento/cajas',
        '/talento/api/cajas',
        '/talento/api/cajas/latest',
        '/talento/api/cajas/bonus-log',
        '/talento/api/cajas/settings',
    ],
    'talento.caja.manage' => [
        '/talento/api/cajas',
        '/talento/api/cajas/settings',
        '/talento/api/cajas/bonus-log/{workOrderId}/evaluate',
    ],
    'talento.health_bonus.view' => [
        '/talento/api/cajas/bonus-log',
    ],
    'talento.warranty.view' => [
        '/talento/api/warranty',
        '/talento/api/warranty/classify',
        '/talento/api/warranty/{workOrderId}/overrides',
    ],
    'talento.warranty.override' => [
        '/talento/api/warranty/{workOrderId}/override',
    ],
    'talento.survey.view' => [
        '/talento/api/campo/{workOrderId}/encuesta',
    ],
    'talento.survey.manage' => [
        '/talento/api/campo/{workOrderId}/encuesta',
    ],
    'talento.embajadores.view' => [
        '/talento/embajadores-colabs',
        '/talento/api/colaboradores/{id}/embajador-data',
    ],
    'talento.config.evidencias' => [
        '/talento/config/evidencias',
        '/talento/api/config/evidencias',
        '/talento/api/config/evidencias/toggle',
    ],
    'talento.activations.manage' => [
        '/talento/api/campo/{workOrderId}/activacion',
        '/talento/api/campo/{workOrderId}/activar',
    ],

    // ══════════════════════════════════════════════════════════════════════════
    // Integration Hub (marketing)
    // ══════════════════════════════════════════════════════════════════════════
    'ia_view_chat' => [
        '/ia-chat',
        '/ia/chat/**',
    ],
    'ia_view_prompts' => [
        '/ia/prompts',
        '/ia/prompts/**',
    ],

    // Integration Hub accesible para configurar API keys
    'view_integration_hub' => [
        '/integraciones',
        '/integraciones/**',
    ],

    // ══════════════════════════════════════════════════════════════════════════
    // Domiciliación a Tarjeta (addon-domiciliacion) — acceso admin
    // Las rutas públicas /d/{token} no pasan por check_route_permission.
    // ══════════════════════════════════════════════════════════════════════════
    'domiciliacion.view' => [
        '/domiciliacion/clientes/{clientId}',
    ],
    'domiciliacion.manage' => [
        '/domiciliacion/clientes/{clientId}',
        '/domiciliacion/clientes/{clientId}/{cardId}',
    ],
    'domiciliacion.links' => [
        '/domiciliacion/clientes/{clientId}/liga',
    ],

    // ══════════════════════════════════════════════════════════════════════════
    // MegaFamilia (addon-megafamilia) — acceso web admin
    // ══════════════════════════════════════════════════════════════════════════
    'megafamilia_view' => [
        '/megafamilia/**',
        '/api/megafamilia/**',
    ],
    'megafamilia_admin' => [
        '/megafamilia/**',
        '/api/megafamilia/**',
    ],
    'megafamilia_support' => [
        '/megafamilia/**',
        '/api/megafamilia/**',
    ],

];
