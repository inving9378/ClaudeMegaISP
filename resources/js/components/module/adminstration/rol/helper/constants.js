import { ref } from "vue";

export const fieldsJson = {
    dashboard: [
        {
            field: "dashboard_view_dashboard",
            label: "Ver dashboard",
            value: false,
        },
        {
            field: "dashboard_view_card_client_inline",
            label: "Ver card de clientes en linea",
            value: false,
            depend: "dashboard_view_dashboard",
        },
        {
            field: "dashboard_view_card_client_new",
            label: "Ver card de clientes nuevos",
            value: false,
            depend: "dashboard_view_dashboard",
        },
        {
            field: "dashboard_view_card_tickets_open_new",
            label: "Ver card de tickets nuevos/abiertos",
            value: false,
            depend: "dashboard_view_dashboard",
        },
        {
            field: "dashboard_view_card_device_not_responding",
            label: "Ver card de dispositivos sin respuesta",
            value: false,
            depend: "dashboard_view_dashboard",
        },
        {
            field: "dashboard_view_block_client",
            label: "Ver bloque de clientes",
            value: false,
            depend: "dashboard_view_dashboard",
        },
        {
            field: "dashboard_view_block_ticket",
            label: "Ver bloque de tickets",
            value: false,
            depend: "dashboard_view_dashboard",
        },
        {
            field: "dashboard_view_block_finance",
            label: "Ver bloque de finanzas",
            value: false,
            depend: "dashboard_view_dashboard",
        },

        {
            field: "dashboard_view_block_server_status",
            label: "Ver bloque de Informacion del Servidor",
            value: false,
            depend: "dashboard_view_dashboard",
        },
        {
            field: "dashboard_view_info_invoice_transaction",
            label: "Ver bloque Informacion Facturas Transacciones",
            value: false,
            depend: "dashboard_view_dashboard",
        },
    ],
    plan: [
        {
            field: "plan_view_internet",
            label: "Ver listado de planes de internet",
            value: false,
        },
        {
            field: "plan_add_internet",
            label: "Agregar Plan de Internet",
            value: false,
            depend: "plan_view_internet",
        },
        {
            field: "plan_edit_internet",
            label: "Editar Plan de Internet",
            value: false,
            depend: "plan_view_internet",
        },
        {
            field: "plan_delete_internet",
            label: "Eliminar Plan de Internet",
            value: false,
            depend: "plan_view_internet",
        },
        {
            field: "plan_export_internet",
            label: "Exportar datos de la tabla",
            value: false,
            depend: "plan_view_internet",
        },
        // ---------------------------------
        {
            field: "plan_view_voz",
            label: "Ver listado de planes de voz",
            value: false,
        },
        {
            field: "plan_add_voz",
            label: "Agregar Plan de Voz",
            value: false,
            depend: "plan_view_voz",
        },
        {
            field: "plan_edit_voz",
            label: "Editar Plan de Voz",
            value: false,
            depend: "plan_view_voz",
        },
        {
            field: "plan_delete_voz",
            label: "Eliminar Plan de Voz",
            value: false,
            depend: "plan_view_voz",
        },
        {
            field: "plan_export_voz",
            label: "Exportar datos de la tabla",
            value: false,
            depend: "plan_view_voz",
        },
        // ---------------------------------
        {
            field: "plan_view_custom",
            label: "Ver listado de planes custom",
            value: false,
        },
        {
            field: "plan_add_custom",
            label: "Agregar Plan de Personalizado",
            value: false,
            depend: "plan_view_custom",
        },
        {
            field: "plan_edit_custom",
            label: "Editar Plan de Personalizado",
            value: false,
            depend: "plan_view_custom",
        },
        {
            field: "plan_delete_custom",
            label: "Eliminar Plan de Personalizado",
            value: false,
            depend: "plan_view_custom",
        },
        {
            field: "plan_export_custom",
            label: "Exportar datos de la tabla",
            value: false,
            depend: "plan_view_custom",
        },
        // ---------------------------------
        {
            field: "plan_view_package",
            label: "Ver listado de paquetes",
            value: false,
        },
        {
            field: "plan_add_package",
            label: "Agregar Paquete",
            value: false,
            depend: "plan_view_package",
        },
        {
            field: "plan_edit_package",
            label: "Editar Paquete",
            value: false,
            depend: "plan_view_package",
        },
        {
            field: "plan_delete_package",
            label: "Eliminar Paquete",
            value: false,
            depend: "plan_view_package",
        },
        {
            field: "plan_export_package",
            label: "Exportar datos de la tabla",
            value: false,
            depend: "plan_view_package",
        },
    ],

    crm: [
        {
            field: "crm_view_crm",
            label: "Ver listado de clientes potenciales",
            value: false,
        },
        {
            field: "crm_add_crm",
            label: "Agregar cliente potencial",
            value: false,
            depend: "crm_view_crm",
        },
        {
            field: "crm_edit_crm",
            label: "Editar cliente potencial",
            value: false,
            depend: "crm_view_crm",
        },
        {
            field: "crm_delete_crm",
            label: "Eliminar cliente potencial",
            value: false,
            depend: "crm_view_crm",
        },
        {
            field: "crm_export_crm",
            label: "Exportar datos de la tabla",
            value: false,
            depend: "crm_view_crm",
        },
        {
            field: "crm_convert_crm",
            label: "Convertir Cliente Potencial",
            value: false,
            depend: "crm_view_crm",
        },
        //  Informacion
        {
            field: "crm_information_view_tab_crm",
            label: "Ver tab de información",
            value: false,
        },
        {
            field: "crm_information",
            label: "Ver información del cliente potencial",
            value: false,
            depend: "crm_information_view_tab_crm",
        },
        //  Geolocalización
        {
            field: "crm_information_geolocation_crm",
            label: "Ver información de geolocalización del CRM",
            value: false,
        },
        //  Documentos
        {
            field: "crm_document_view_tab_crm",
            label: "Ver tab de documentos",
            value: false,
        },
        {
            field: "crm_document",
            label: "CRM documentos",
            value: false,
            depend: "crm_document_view_tab_crm",
        },
        {
            field: "crm_document_view_crm",
            label: "Ver documentación de crm",
            value: false,
            depend: "crm_document_view_tab_crm",
        },
        {
            field: "crm_document_add_crm",
            label: "Agregar documentación de crm",
            value: false,
            depend: "crm_document_view_tab_crm",
        },
        {
            field: "crm_document_edit_crm",
            label: "Editar documentación de crm",
            value: false,
            depend: "crm_document_view_tab_crm",
        },
        {
            field: "crm_document_delete_crm",
            label: "Eliminar documentación de crm",
            value: false,
            depend: "crm_document_view_tab_crm",
        },
    ],

    client: [
        {
            field: "client_view_dashboard",
            label: "Ver dashboard",
            value: false,
        },
        {
            field: "client_view_client",
            label: "Ver listado de clientes",
            value: false,
        },
        {
            field: "client_add_client",
            label: "Agregar cliente",
            value: false,
            depend: "client_view_client",
        },
        {
            field: "client_edit_client",
            label: "Editar cliente",
            value: false,
            depend: "client_view_client",
        },
        {
            field: "client_delete_client",
            label: "Eliminar cliente",
            value: false,
            depend: "client_view_client",
        },
        {
            field: "client_export_client",
            label: "Exportar datos de la tabla",
            value: false,
            depend: "client_view_client",
        },

        {
            field: "client_edit_fecha_corte",
            label: "Editar fecha de corte",
            value: false,
            depend: "client_view_client",
        },
        {
            field: "client_edit_fecha_pago",
            label: "Editar fecha de pago",
            value: false,
            depend: "client_view_client",
        },

        {
            field: "client_edit_balance",
            label: "Editar saldo de la cuenta",
            value: false,
            depend: "client_view_client",
        },
        {
            field: "client_edit_id",
            label: "Editar id del cliente",
            value: false,
            depend: "client_view_client",
        },
        {
            field: "client_update_status",
            label: "Actualizar estado del cliente",
            value: false,
            depend: "client_view_client",
        },
        // Geolocalizacion
        {
            field: "client_information_geolocation_client",
            label: "Ver información de geolocalización del cliente",
            value: false,
        },
        // Tabs
        {
            field: "client_information_view_tab_client",
            label: "Ver tab de información",
            value: false,
        },
        {
            field: "client_service_view_tab_client",
            label: "Ver tab de servicios",
            value: false,
            depend: "client_information_view_tab_client",
        },
        {
            field: "client_payroll_view_tab_client",
            label: "Ver tab de pagos",
            value: false,
            depend: "client_information_view_tab_client",
        },
        // Servicios de internet
        {
            field: "client_service_internet",
            label: "Servicios de internet",
            value: false,
        },
        {
            field: "client_service_internet_view_client",
            label: "Ver servicio de internet",
            value: false,
            depend: "client_service_internet",
        },
        {
            field: "client_service_internet_add_client",
            label: "Agregar servicio de internet",
            value: false,
            depend: "client_service_internet",
        },
        {
            field: "client_service_internet_edit_client",
            label: "Editar servicio de internet",
            value: false,
            depend: "client_service_internet",
        },
        {
            field: "client_service_internet_edit_client",
            label: "Eliminar servicio de internet",
            value: false,
            depend: "client_service_internet",
        },
        // Servicios de voz
        {
            field: "client_service_voz",
            label: "Servicios de voz",
            value: false,
        },
        {
            field: "client_service_voz_view_client",
            label: "Ver servicio de voz",
            value: false,
            depend: "client_service_voz",
        },
        {
            field: "client_service_voz_add_client",
            label: "Agregar servicio de voz",
            value: false,
            depend: "client_service_voz",
        },
        {
            field: "client_service_voz_edit_client",
            label: "Editar servicio de voz",
            value: false,
            depend: "client_service_voz",
        },
        {
            field: "client_service_voz_delete_client",
            label: "Eliminar servicio de voz",
            value: false,
            depend: "client_service_voz",
        },
        //Custom
        {
            field: "client_service_custom",
            label: "Servicios custom",
            value: false,
        },
        {
            field: "client_service_custom_view_client",
            label: "Ver servicio custom",
            value: false,
            depend: "client_service_custom",
        },
        {
            field: "client_service_custom_add_client",
            label: "Agregar servicio custom",
            value: false,
            depend: "client_service_custom",
        },
        {
            field: "client_service_custom_edit_client",
            label: "Editar servicio custom",
            value: false,
            depend: "client_service_custom",
        },
        {
            field: "client_service_custom_delete_client",
            label: "Eliminar servicio custom",
            value: false,
            depend: "client_service_custom",
        },
        //  Paquetes
        {
            field: "client_service_bundle",
            label: "Servicios de paquetes",
            value: false,
        },
        {
            field: "client_service_bundle_view_client",
            label: "Ver paquete",
            value: false,
            depend: "client_service_bundle",
        },
        {
            field: "client_service_bundle_add_client",
            label: "Agregar paquete",
            value: false,
            depend: "client_service_bundle",
        },
        {
            field: "client_service_bundle_edit_client",
            label: "Editar paquete",
            value: false,
            depend: "client_service_bundle",
        },
        {
            field: "client_service_bundle_delete_client",
            label: "Eliminar paquete",
            value: false,
            depend: "client_service_bundle",
        },
        {
            field: "client_service_bundle_change_client",
            label: "Cambiar Tarifa",
            value: false,
            depend: "client_service_bundle",
        },
        //  Pagos de los clientes
        {
            field: "client_billing_view_tab_client",
            label: "Ver tab de Facturación",
            value: false,
        },
        {
            field: "client_payroll_payment_view_client",
            label: "Ver pagos",
            value: false,
            depend: "client_billing_view_tab_client",
        },
        {
            field: "client_payroll_payment_add_client",
            label: "Agregar pago",
            value: false,
            depend: "client_billing_view_tab_client",
        },
        {
            field: "client_payroll_payment_edit_client",
            label: "Editar pago",
            value: false,
            depend: "client_billing_view_tab_client",
        },
        {
            field: "client_payroll_payment_delete_client",
            label: "Eliminar pago",
            value: false,
            depend: "client_billing_view_tab_client",
        },
        //Transacciones

        {
            field: "client_billing_transaction_client",
            label: "Ver Transacciones",
            value: false,
            depend: "client_billing_view_tab_client",
        },
        {
            field: "client_billing_transaction_add",
            label: "Agregar Transacciones",
            value: false,
            depend: "client_billing_view_tab_client",
        },
        {
            field: "client_billing_transaction_edit",
            label: "Editar Transacciones",
            value: false,
            depend: "client_billing_view_tab_client",
        },
        {
            field: "client_billing_transaction_delete",
            label: "Eliminar Transacciones",
            value: false,
            depend: "client_billing_view_tab_client",
        },
        //Facturas
        {
            field: "client_billing_invoice_client",
            label: "Ver Facturas",
            value: false,
            depend: "client_billing_view_tab_client",
        },
        {
            field: "client_billing_invoice_add",
            label: "Agregar Facturas",
            value: false,
            depend: "client_billing_view_tab_client",
        },
        {
            field: "client_billing_invoice_edit",
            label: "Editar Facturas",
            value: false,
            depend: "client_billing_view_tab_client",
        },
        {
            field: "client_billing_invoice_delete",
            label: "Eliminar Facturas",
            value: false,
            depend: "client_billing_view_tab_client",
        },
        {
            field: "invoice_send_invoice",
            label: "Enviar Factura por Correo",
            value: false,
            depend: "client_billing_view_tab_client",
        },
        {
            field: "invoice_print_invoice",
            label: "Imprimir Factura",
            value: false,
            depend: "client_billing_view_tab_client",
        },

        //Documentos
        {
            field: "client_document_view_tab_client",
            label: "Ver tab de documentos",
            value: false,
        },
        {
            field: "client_document_view_client",
            label: "Ver Documentod de cliente",
            value: false,
            depend: "client_document_view_tab_client",
        },
        {
            field: "client_document_add_client",
            label: "Agregar Documents de cliente",
            value: false,
            depend: "client_document_view_tab_client",
        },
        {
            field: "client_document_edit_client",
            label: "Editar Documents de cliente",
            value: false,
            depend: "client_document_view_tab_client",
        },
        {
            field: "client_document_delete_client",
            label: "Eliminar Documents de cliente",
            value: false,
            depend: "client_document_view_tab_client",
        },
    ],

    seller: [
        {
            field: "seller_view_dashboard",
            label: "Ver dashboard",
            value: false,
        },
        {
            field: "seller_view_seller",
            label: "Ver listado de vendedores",
            value: false,
        },
        {
            field: "seller_add_seller",
            label: "Agregar vendedor",
            value: false,
            depend: "seller_view_seller",
        },
        {
            field: "seller_view_panel",
            label: "Ver panel de información",
            value: false,
        },
        {
            field: "seller_view_prospects",
            label: "Ver Prospectos",
            value: false,
        },
        {
            field: "seller_view_sales",
            label: "Ver Listado De Ventas",
            value: false,
        },
        {
            field: "seller_view_statics",
            label: "Ver Estadísticas",
            value: false,
        },
        {
            field: "seller_view_billing",
            label: "Ver Facturación",
            value: false,
        },
        {
            field: "seller_follow_payment_client",
            label: "Ver Seguimiento de pago Clientes",
            value: false,
            depend: "seller_view_billing",
        },
        {
            field: "seller_view_all_payments_for_seller",
            label: "Ver Pagos del vendedor",
            value: false,
            depend: "seller_view_billing",
        },
        {
            field: "seller_view_all_transactions_for_seller",
            label: "Ver Transacciones del vendedor",
            value: false,
            depend: "seller_view_billing",
        },
        //corte mostrador
        {
            field: "seller_cuts",
            label: "Ver corte mostrador",
            value: false,
        },
        {
            field: "seller_cuts_add_installation",
            label: "Agregar instalación",
            value: false,
            depend: "seller_cuts",
        },
        {
            field: "seller_cuts_edit_installation",
            label: "Modificar instalación",
            value: false,
            depend: "seller_cuts",
        },
        {
            field: "seller_cuts_delete_installation",
            label: "Eliminar instalación",
            value: false,
            depend: "seller_cuts",
        },
        {
            field: "seller_cuts_add_expenses",
            label: "Adicionar gastos",
            value: false,
            depend: "seller_cuts",
        },
        {
            field: "seller_cuts_edit_expenses",
            label: "Modificar gastos",
            value: false,
            depend: "seller_cuts",
        },
        {
            field: "seller_cuts_delete_expenses",
            label: "Eliminar gastos",
            value: false,
            depend: "seller_cuts",
        },
        {
            field: "seller_cuts_add_extra_icome",
            label: "Agregar ingresos extras",
            value: false,
            depend: "seller_cuts",
        },
        {
            field: "seller_cuts_edit_extra_icome",
            label: "Modificar ingresos extras",
            value: false,
            depend: "seller_cuts",
        },
        {
            field: "seller_cuts_delete_extra_icome",
            label: "Eliminar ingresos extras",
            value: false,
            depend: "seller_cuts",
        },
        {
            field: "seller_cuts_add_comments",
            label: "Agregar observaciones",
            value: false,
            depend: "seller_cuts",
        },
        {
            field: "seller_cuts_edit_comments",
            label: "Modificar observaciones",
            value: false,
            depend: "seller_cuts",
        },
        {
            field: "seller_cuts_delete_comments",
            label: "Eliminar observaciones",
            value: false,
            depend: "seller_cuts",
        },
        {
            field: "seller_cuts_close_box",
            label: "Cerrar caja",
            value: false,
            depend: "seller_cuts",
        },

        {
            field: "selleritems_view_selleritems",
            label: "Ver Articulos del Vendedor",
            value: false,
        },
    ],

    ticket: [
        {
            field: "ticket_view_dashboard",
            label: "Ver dashboard",
            value: false,
        },
        // -----------------------------
        {
            field: "ticket_view_open",
            label: "Ver listado de tickets abiertos",
            value: false,
        },
        {
            field: "ticket_add_open",
            label: "Agregar ticket",
            value: false,
            depend: "ticket_view_open",
        },
        {
            field: "ticket_edit_open",
            label: "Editar ticket",
            value: false,
            depend: "ticket_view_open",
        },
        {
            field: "ticket_delete_open",
            label: "Eliminar ticket",
            value: false,
            depend: "ticket_view_open",
        },
        {
            field: "ticket_export_open",
            label: "Exportar datos de la tabla",
            value: false,
            depend: "ticket_view_open",
        },
        // -------------------------------
        {
            field: "ticket_view_close",
            label: "Ver listado de tickets cerrados",
            value: false,
        },
        {
            field: "ticket_add_close",
            label: "Agregar ticket",
            value: false,
            depend: "ticket_view_close",
        },
        {
            field: "ticket_edit_close",
            label: "Editar ticket",
            value: false,
            depend: "ticket_view_close",
        },
        {
            field: "ticket_delete_close",
            label: "Eliminar ticket",
            value: false,
            depend: "ticket_view_close",
        },
        {
            field: "ticket_export_close",
            label: "Exportar datos de la tabla",
            value: false,
            depend: "ticket_view_close",
        },
        // -------------------------------
        {
            field: "ticket_view_recycling",
            label: "Ver listado de tickets reciclados",
            value: false,
        },
        {
            field: "ticket_add_recycling",
            label: "Agregar ticket",
            value: false,
            depend: "ticket_view_recycling",
        },
        {
            field: "ticket_edit_recycling",
            label: "Editar ticket",
            value: false,
            depend: "ticket_view_recycling",
        },
        {
            field: "ticket_delete_recycling",
            label: "Eliminar ticket",
            value: false,
            depend: "ticket_view_recycling",
        },
        {
            field: "ticket_export_recycling",
            label: "Exportar datos de la tabla",
            value: false,
            depend: "ticket_view_recycling",
        },
    ],

    finance: [
        {
            field: "finance_view_transactions",
            label: "Ver listado de transacciones",
            value: false,
        },
        {
            field: "finance_edit_transactions",
            label: "Editar transacción",
            value: false,
            depend: "finance_view_transactions",
        },
        {
            field: "finance_delete_transactions",
            label: "Eliminar transacción",
            value: false,
            depend: "finance_view_transactions",
        },
        {
            field: "finance_export_transactions",
            label: "Exportar datos de la tabla",
            value: false,
            depend: "finance_view_transactions",
        },
        // -----------------------------
        {
            field: "finance_view_billing",
            label: "Ver listado de facturación",
            value: false,
        },
        {
            field: "finance_edit_billing",
            label: "Editar factura",
            value: false,
            depend: "finance_view_billing",
        },
        {
            field: "finance_delete_billing",
            label: "Eliminar factura",
            value: false,
            depend: "finance_view_billing",
        },
        {
            field: "finance_export_billing",
            label: "Exportar tabla de datos",
            value: false,
            depend: "finance_view_billing",
        },
        // -----------------------------
        {
            field: "finance_view_payments",
            label: "Ver listado de pagos",
            value: false,
        },
        {
            field: "finance_edit_payments",
            label: "Editar pago",
            value: false,
            depend: "finance_view_payments",
        },
        {
            field: "finance_delete_payments",
            label: "Eliminar pago",
            value: false,
            depend: "finance_view_payments",
        },
        {
            field: "finance_export_payments",
            label: "Exportar tabla de datos",
            value: false,
            depend: "finance_view_payments",
        },

        {
            field: "finance_view_invoices",
            label: "Ver listado de Facturas",
            value: false,
        },

        {
            field: "invoice_add_invoice",
            label: "Agregar Factura",
            value: false,
            depend: "finance_view_invoices",
        },
        {
            field: "invoice_send_invoice",
            label: "Enviar Factura por Correo",
            value: false,
            depend: "finance_view_invoices",
        },
        {
            field: "invoice_mark_as_paid_invoice",
            label: "Marcar factura como pagada",
            value: false,
            depend: "finance_view_invoices",
        },
        {
            field: "invoice_print_invoice",
            label: "Imprimir Factura",
            value: false,
            depend: "finance_view_invoices",
        },

        {
            field: "finance_view_general_accounting",
            label: "Ver Contabilidad General",
            value: false,
        },
        {
            field: "finance_edit_general_accounting_expense",
            label: "Editar Operacion de Gasto",
            value: false,
            depend: "finance_view_general_accounting",
        },

        {
            field: "finance_delete_general_accounting_expense",
            label: "Eliminar Operacion de Gasto",
            value: false,
            depend: "finance_view_general_accounting",
        },
        {
            field: "finance_view_general_accounting_expense",
            label: "Ver Operaciones de Gasto",
            value: false,
            depend: "finance_view_general_accounting",
        },
        {
            field: "finance_add_general_accounting_expense",
            label: "Agregar Operacion de Gasto",
            value: false,
            depend: "finance_view_general_accounting",
        },
        {
            field: "finance_view_general_accounting_income",
            label: "Ver Operaciones de Ingreso",
            value: false,
            depend: "finance_view_general_accounting",
        },
        {
            field: "finance_add_general_accounting_income",
            label: "Agregar Operacion de Ingreso",
            value: false,
            depend: "finance_view_general_accounting",
        },
    ],

    maps: [
        {
            field: "maps_view_maps",
            label: "Ver mapa",
            value: false,
        },
        {
            field: "maps_folder_add",
            label: "Adicionar carpeta",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_folder_edit",
            label: "Modificar carpeta",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_folder_remove",
            label: "Eliminar carpeta",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_region_add",
            label: "Adicionar región",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_region_edit",
            label: "Modificar región",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_region_remove",
            label: "Eliminar región",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_route_add",
            label: "Adicionar ruta",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_route_edit",
            label: "Modificar ruta",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_route_remove",
            label: "Eliminar ruta",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_service_box_add",
            label: "Adicionar caja de servicio",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_service_box_edit",
            label: "Modificar caja de servicio",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_service_box_remove",
            label: "Eliminar caja de servicio",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_junction_box_add",
            label: "Adicionar caja de empalme",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_junction_box_edit",
            label: "Modificar caja de empalme",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_junction_box_remove",
            label: "Eliminar caja de empalme",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_pack_add",
            label: "Adicionar pack",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_pack_edit",
            label: "Modificar pack",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_pack_remove",
            label: "Eliminar pack",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_cupboard_add",
            label: "Adicionar armario",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_cupboard_edit",
            label: "Modificar armario",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_cupboard_remove",
            label: "Eliminar armario",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_source_add",
            label: "Adicionar fuente",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_source_edit",
            label: "Modificar fuente",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_source_remove",
            label: "Eliminar fuente",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_pole_add",
            label: "Adicionar poste",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_pole_edit",
            label: "Modificar poste",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_pole_remove",
            label: "Eliminar poste",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_site_add",
            label: "Adicionar site",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_site_edit",
            label: "Modificar site",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_site_remove",
            label: "Eliminar site",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_building_add",
            label: "Adicionar edificio",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_building_edit",
            label: "Modificar edificio",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_building_remove",
            label: "Eliminar edificio",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_client_add",
            label: "Adicionar cliente",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_client_edit",
            label: "Modificar cliente",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_client_remove",
            label: "Eliminar cliente",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_note_add",
            label: "Adicionar nota",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_note_edit",
            label: "Modificar nota",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_note_remove",
            label: "Eliminar nota",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_change_classification",
            label: "Convertir a red/proyecto",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_kmz_load",
            label: "Cargar archivo kmz",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_kmz_edit",
            label: "Editar objeto kmz",
            depend: "maps_view_maps",
            value: false,
        },
        {
            field: "maps_kmz_remove",
            label: "Eliminar objeto kmz",
            depend: "maps_view_maps",
            value: false,
        },
    ],

    olts: [
        {
            field: "olt_view",
            label: "Ver OLT",
            value: false,
        },
        {
            field: "onu_add",
            label: "Adicionar ONU",
            depend: "olt_view",
            value: false,
        },
        {
            field: "onu_edit",
            label: "Modificar ONU",
            depend: "olt_view",
            value: false,
        },
        {
            field: "onu_enable_disable",
            label: "Habilitar/Deshabilitar ONU",
            depend: "olt_view",
            value: false,
        },
        {
            field: "onu_resync",
            label: "Resincronizar ONU",
            depend: "olt_view",
            value: false,
        },
        {
            field: "onu_default",
            label: "Restaurar ONU de fábrica",
            depend: "olt_view",
            value: false,
        },
        {
            field: "onu_reboot",
            label: "Reiniciar ONU",
            depend: "olt_view",
            value: false,
        },
        {
            field: "onu_remove",
            label: "Eliminar ONU",
            depend: "olt_view",
            value: false,
        },
        {
            field: "zone_add",
            label: "Adicionar zona",
            depend: "olt_view",
            value: false,
        },
        {
            field: "onu_type_add",
            label: "Adicionar tipo ONU",
            depend: "olt_view",
            value: false,
        },
        {
            field: "odb_add",
            label: "Adicionar ODB",
            depend: "olt_view",
            value: false,
        },
        {
            field: "vlan_add",
            label: "Adicionar VLAN",
            depend: "olt_view",
            value: false,
        },
        {
            field: "sync_from_api",
            label: "Sincronizar datos desde API",
            depend: "olt_view",
            value: false,
        },
    ],

    scheduling: [
        {
            field: "scheduling_view_scheduling",
            label: "Ver Tareas",
            value: false,
        },
        //Proyectos
        {
            field: "scheduling_project_view_project",
            label: "Ver Proyectos",
            value: false,
        },
        {
            field: "scheduling_project_create",
            label: "Crear Proyectos",
            value: false,
            depend: "scheduling_project_view_project",
        },
        {
            field: "scheduling_project_update",
            label: "Editar Proyectos",
            value: false,
            depend: "scheduling_project_view_project",
        },
        {
            field: "scheduling_project_delete",
            label: "Eliminar Proyectos",
            value: false,
            depend: "scheduling_project_view_project",
        },
        //Tareas
        {
            field: "task_view_task",
            label: "Ver Tareas",
            value: false,
        },

        {
            field: "task_add_task",
            label: "Crear Tareas",
            value: false,
            depend: "task_view_task",
        },
        {
            field: "task_edit_task",
            label: "Editar Tareas",
            value: false,
            depend: "task_view_task",
        },
        {
            field: "task_delete_task",
            label: "Eliminar Tareas",
            value: false,
            depend: "task_view_task",
        },
        {
            field: "task_view_full_task",
            label: "Ver Todas las Tareas",
            value: false,
            depend: "task_view_task",
        },
        {
            field: "task_archive_task",
            label: "Archivar Tareas",
            value: false,
            depend: "task_view_task",
        },
        {
            field: "task_view_archived_task",
            label: "Ver Tareas archivadas",
            value: false,
            depend: "task_view_task",
        },

        {
            field: "task_filter_project",
            label: "Ver Filtro Proyecto",
            value: false,
            depend: "task_view_task",
        },

        {
            field: "task_filter_status",
            label: "Ver Filtro Estado",
            value: false,
            depend: "task_view_task",
        },

        {
            field: "task_filter_partner",
            label: "Ver Filtro Socio",
            value: false,
            depend: "task_view_task",
        },

        {
            field: "task_filter_assigned_to",
            label: "Ver Filtro Asignado a",
            value: false,
            depend: "task_view_task",
        },
        {
            field: "task_filter_filter",
            label: "Ver Filtro Tabla",
            value: false,
            depend: "task_view_task",
        },

        //Calendario
        {
            field: "scheduling_view_calendar",
            label: "Ver Calendario",
            value: false,
        },
        {
            field: "calendar_filter_project",
            label: "Ver Filtro Proyecto",
            value: false,
            depend: "scheduling_view_calendar",
        },
        {
            field: "calendar_filter_status",
            label: "Ver Filtro Estado",
            value: false,
            depend: "scheduling_view_calendar",
        },
        {
            field: "calendar_filter_partner",
            label: "Ver Filtro Socio",
            value: false,
            depend: "scheduling_view_calendar",
        },
        {
            field: "calendar_filter_assigned_to",
            label: "Ver Filtro Asignado a",
            value: false,
            depend: "scheduling_view_calendar",
        },
        {
            field: "calendar_filter_filter",
            label: "Ver Filtro Tabla",
            value: false,
            depend: "scheduling_view_calendar",
        },
    ],

    network: [
        {
            field: "router_view_router",
            label: "Ver lista de enrutadores",
            value: false,
        },
        {
            field: "router_add_router",
            label: "Agregar router",
            value: false,
            depend: "router_view_router",
        },
        {
            field: "router_edit_router",
            label: "Editar router",
            value: false,
            depend: "router_view_router",
        },
        {
            field: "router_delete_router",
            label: "Eliminar router",
            value: false,
            depend: "router_view_router",
        },
        {
            field: "router_export_router",
            label: "Exportar datos de la tabla",
            value: false,
            depend: "router_view_router",
        },
        // -----------------------------
        {
            field: "ipv4_view_ipv4",
            label: "Ver listado de Ipv4",
            value: false,
        },
        {
            field: "ipv4_add_ipv4",
            label: "Agregar red",
            value: false,
            depend: "ipv4_view_ipv4",
        },
        {
            field: "ipv4_edit_ipv4",
            label: "Editar red",
            value: false,
            depend: "ipv4_view_ipv4",
        },
        {
            field: "ipv4_delete_ipv4",
            label: "Eliminar red",
            value: false,
            depend: "ipv4_view_ipv4",
        },
        {
            field: "ipv4_export_ipv4",
            label: "Exportar datos de la tabla",
            value: false,
            depend: "ipv4_view_ipv4",
        },
    ],

    inventory: [
        {
            field: "inventory_view_inventory",
            label: "Ver Inventario",
            value: false,
        },
        {
            field: "inventory_item_view_inventory_item",
            label: "Ver Articulos de Inventario",
            value: false,
            depend: "inventory_view_inventory",
        },
        {
            field: "inventory_item_add_inventory_item",
            label: "Agregar Articulo de Inventario",
            value: false,
            depend: "inventory_view_inventory",
        },
        {
            field: "inventory_item_edit_inventory_item",
            label: "Editar Articulo de Inventario",
            value: false,
            depend: "inventory_view_inventory",
        },
        {
            field: "inventory_item_delete_inventory_item",
            label: "Eliminar Articulo de Inventario",
            value: false,
            depend: "inventory_view_inventory",
        },
        {
            field: "inventory_item_type_view_inventory_item_type",
            label: "Ver Tipos de Articulos de Inventario",
            value: false,
            depend: "inventory_view_inventory",
        },
        {
            field: "inventory_item_type_add_inventory_item_type",
            label: "Agregar Tipos de Articulos de Inventario",
            value: false,
            depend: "inventory_view_inventory",
        },
        {
            field: "inventory_item_type_edit_inventory_item_type",
            label: "Editar Tipos de Articulos de Inventario",
            value: false,
            depend: "inventory_view_inventory",
        },
        {
            field: "inventory_item_type_delete_inventory_item_type",
            label: "Eliminar Tipos de Articulos de Inventario",
            value: false,
            depend: "inventory_view_inventory",
        },

        {
            field: "inventory_movement_view_inventory_movement",
            label: "Ver Movimientos de Inventario",
            value: false,
            depend: "inventory_view_inventory",
        },
        {
            field: "inventory_movement_add_inventory_movement",
            label: "Agregar Movimientos de Inventario",
            value: false,
            depend: "inventory_view_inventory",
        },
        {
            field: "inventory_movement_edit_inventory_movement",
            label: "Editar Movimientos de Inventario",
            value: false,
            depend: "inventory_view_inventory",
        },
        {
            field: "inventory_movement_delete_inventory_movement",
            label: "Eliminar Movimientos de Inventario",
            value: false,
            depend: "inventory_view_inventory",
        },

        //Almacen
        {
            field: "supervision_store",
            label: "Supervisor de Almacen",
            value: false,
            depend: "inventory_store_view_inventory_store",
        },
        {
            field: "inventory_store_view_inventory_store",
            label: "Ver Almacen",
            value: false,
        },
        {
            field: "inventory_store_add_inventory_store",
            label: "Agregar Almacen",
            value: false,
            depend: "inventory_store_view_inventory_store",
        },
        {
            field: "inventory_store_edit_inventory_store",
            label: "Editar Almacen",
            value: false,
            depend: "inventory_store_view_inventory_store",
        },
        {
            field: "inventory_store_delete_inventory_store",
            label: "Eliminar Almacen",
            value: false,
            depend: "inventory_store_view_inventory_store",
        },
        //Zonas
        {
            field: "store_zone_view_store_zone",
            label: "Ver Zonas de Almacen",
            value: false,
            depend: "inventory_view_inventory",
        },
        {
            field: "store_zone_add_store_zone",
            label: "Agregar Zonas de Almacen",
            value: false,
            depend: "inventory_view_inventory",
        },
        {
            field: "store_zone_edit_store_zone",
            label: "Editar Zonas de Almacen",
            value: false,
            depend: "inventory_view_inventory",
        },
        {
            field: "store_zone_delete_store_zone",
            label: "Eliminar Zonas de Almacen",
            value: false,
            depend: "inventory_view_inventory",
        },

        //Zonas
        {
            field: "inventory_item_custom_model_view_inventory_item_custom_model",
            label: "Ver Articulos Custom",
            value: false,
            depend: "inventory_view_inventory",
        },
        {
            field: "inventory_item_custom_model_add_inventory_item_custom_model",
            label: "Agregar Articulos Custom",
            value: false,
            depend: "inventory_view_inventory",
        },
        {
            field: "inventory_item_custom_model_edit_inventory_item_custom_model",
            label: "Editar Articulos Custom",
            value: false,
            depend: "inventory_view_inventory",
        },
        {
            field: "inventory_item_custom_model_delete_inventory_item_custom_model",
            label: "Eliminar Articulos Custom",
            value: false,
            depend: "inventory_view_inventory",
        },
    ],

    administration: [
        {
            field: "admin_view_module",
            label: "Ver módulo de administración",
            value: false,
        },
        // Estado
        {
            field: "state_view_state",
            label: "Ver opción de estado",
            value: false,
        },
        {
            field: "state_add_state",
            label: "Agregar estado",
            value: false,
            depend: "state_view_state",
        },
        {
            field: "state_edit_state",
            label: "Editar estado",
            value: false,
            depend: "state_view_state",
        },
        {
            field: "state_delete_state",
            label: "Eliminar estado",
            value: false,
            depend: "state_view_state",
        },
        {
            field: "state_export_state",
            label: "Exportar datos de la tabla",
            value: false,
            depend: "state_view_state",
        },
        // Municipio
        {
            field: "municipality_view_municipality",
            label: "Ver opción de municipio",
            value: false,
        },
        {
            field: "municipality_add_municipality",
            label: "Agregar municipio",
            value: false,
            depend: "municipality_view_municipality",
        },
        {
            field: "municipality_edit_municipality",
            label: "Editar municipio",
            value: false,
            depend: "municipality_view_municipality",
        },
        {
            field: "municipality_delete_municipality",
            label: "Eliminar municipio",
            value: false,
            depend: "municipality_view_municipality",
        },
        {
            field: "municipality_export_municipality",
            label: "Exportar datos de la tabla",
            value: false,
            depend: "municipality_view_municipality",
        },
        // Colonia
        {
            field: "colony_view_colony",
            label: "Ver opción de colonia",
            value: false,
        },
        {
            field: "colony_add_colony",
            label: "Agregar colonia",
            value: false,
            depend: "colony_view_colony",
        },
        {
            field: "colony_edit_colony",
            label: "Editar colonia",
            value: false,
            depend: "colony_view_colony",
        },
        {
            field: "colony_delete_colony",
            label: "Eliminar colonia",
            value: false,
            depend: "colony_view_colony",
        },
        {
            field: "colony_export_colony",
            label: "Exportar datos de la tabla",
            value: false,
            depend: "colony_view_colony",
        },
        // Metodo de pago
        {
            field: "method_payment_view_method_payment",
            label: "Ver opción de método de pago",
            value: false,
        },
        {
            field: "method_payment_add_method_payment",
            label: "Agregar método de pago",
            value: false,
            depend: "method_payment_view_method_payment",
        },
        {
            field: "method_payment_edit_method_payment",
            label: "Editar método de pago",
            value: false,
            depend: "method_payment_view_method_payment",
        },
        {
            field: "method_payment_delete_method_payment",
            label: "Eliminar método de pago",
            value: false,
            depend: "method_payment_view_method_payment",
        },
        {
            field: "method_payment_export_method_payment",
            label: "Exportar datos de la tabla",
            value: false,
            depend: "method_payment_view_method_payment",
        },
        // IFT
        {
            field: "ift_view_ift",
            label: "Ver opción de ift",
            value: false,
        },
        {
            field: "ift_add_ift",
            label: "Agregar ift",
            value: false,
            depend: "ift_view_ift",
        },
        {
            field: "ift_edit_ift",
            label: "Editar ift",
            value: false,
            depend: "ift_view_ift",
        },
        {
            field: "ift_delete_ift",
            label: "Eliminar ift",
            value: false,
            depend: "ift_view_ift",
        },
        {
            field: "ift_export_ift",
            label: "Exportar datos de la tabla",
            value: false,
            depend: "ift_view_ift",
        },
        // Paquetes
        {
            field: "package_view_package",
            label: "Ver opción de paquetes",
            value: false,
        },
        // -------------------------------
        {
            field: "admin_view_meganet",
            label: "Ver grupo de Meganet",
            value: false,
        },
        {
            field: "admin_view_registers",
            label: "Ver grupo de Registros",
            value: false,
        },
        {
            field: "admin_view_information",
            label: "Ver grupo de información",
            value: false,
        },
        {
            field: "admin_view_reports",
            label: "Ver grupo de informes",
            value: false,
        },
        {
            field: "admin_view_scripts",
            label: "Ver Scripts Ejecutable",
            value: false,
        },

        //Roles
        {
            field: "role_view_role",
            label: "Ver Roles",
            value: false,
        },
        {
            field: "role_add_role",
            label: "Agregar Rol",
            value: false,
            depend: "role_view_role",
        },
        {
            field: "role_edit_role",
            label: "Editar Rol",
            value: false,
            depend: "role_view_role",
        },
        {
            field: "role_delete_role",
            label: "Eliminar Rol",
            value: false,
            depend: "role_view_role",
        },
        {
            field: "role_permission_role",
            label: "Editar permisos de Rol",
            value: false,
            depend: "role_view_role",
        },

        //Usuarios
        {
            field: "user_view_user",
            label: "Ver Usuarios",
            value: false,
        },
        {
            field: "user_add_user",
            label: "Agregar Usuario",
            value: false,
            depend: "user_view_user",
        },
        {
            field: "user_edit_user",
            label: "Editar Usuario",
            value: false,
            depend: "user_view_user",
        },
        {
            field: "user_delete_user",
            label: "Eliminar Usuario",
            value: false,
            depend: "user_view_user",
        },
        {
            field: "user_permission_user",
            label: "Editar permisos de Usuarios",
            value: false,
            depend: "user_view_user",
        },

        //Documentacion
        {
            field: "documentation_view_documentation",
            label: "Ver documentacion",
            value: false,
        },
        {
            field: "documentation_add_documentation",
            label: "Agregar documentación",
            value: false,
            depend: "documentation_view_documentation",
        },
        {
            field: "documentation_edit_documentation",
            label: "Actualizar documentación",
            value: false,
            depend: "documentation_view_documentation",
        },
        {
            field: "documentation_delete_documentation",
            label: "Eliminar documentación",
            value: false,
            depend: "documentation_view_documentation",
        },
        
        // Sucursal
        {
            field: "view_sucursal",
            label: "Ver sucursales",
            value: false,
        },
        {
            field: "add_sucursal",
            label: "Agregar sucursal",
            value: false,
            depend: "view_sucursal",
        },
        {
            field: "edit_sucursal",
            label: "Editar sucursal",
            value: false,
            depend: "view_sucursal",
        },
        {
            field: "delete_sucursal",
            label: "Eliminar sucursal",
            value: false,
            depend: "view_sucursal",
        },
        {
            field: "export_sucursal",
            label: "Exportar datos de la tabla",
            value: false,
            depend: "view_sucursal",
        },
    ],

    configuration: [
        {
            field: "config_view_module",
            label: "Ver módulo de configuración",
            value: false,
        },
        {
            field: "config_view_system",
            label: "Ver grupo de sistema",
            value: false,
        },
        {
            field: "config_view_main",
            label: "Ver grupo principal",
            value: false,
        },
        {
            field: "config_view_finance",
            label: "Ver grupo de finanzas",
            value: false,
        },
        {
            field: "config_view_finance_notification",
            label: "Editar notificaciones de finanzas",
            value: false,
            depend: "config_view_finance",
        },
        {
            field: "config_view_network_management",
            label: "Ver grupo de gestión de red",
            value: false,
        },
        {
            field: "service_in_address_list_view_service_in_address_list",
            label: "Ver Tabla de Address list",
            value: false,
            depend: "config_view_network_management",
        },
        {
            field: "nomenclature_view_nomenclature",
            label: "Ver Tabla de Nomenclaturas",
            value: false,
            depend: "config_view_network_management",
        },

        {
            field: "config_view_helpdesk",
            label: "Ver grupo helpdesk",
            value: false,
        },
        {
            field: "config_view_scheduling",
            label: "Ver grupo scheduling",
            value: false,
        },
        {
            field: "config_view_potencial_customer",
            label: "Ver grupo de clientes potenciales",
            value: false,
        },
        {
            field: "config_view_inventory",
            label: "Ver grupo de inventario",
            value: false,
        },
        {
            field: "config_view_integrations",
            label: "Ver grupo de integraciones",
            value: false,
        },
        {
            field: "config_view_voice",
            label: "Ver grupo de voz",
            value: false,
        },
        {
            field: "config_view_tools",
            label: "Ver grupo de herramientas",
            value: false,
        },
        {
            field: "config_view_sales",
            label: "Ver grupo de ventas",
            value: false,
        },
        {
            field: "config_view_google_maps",
            label: "Ver grupo google maps",
            value: false,
        },

        //Informacion de la empresa
        {
            field: "company_information_view_company_information",
            label: "Ver información de la empresa",
            value: false,
        },
        {
            field: "company_information_add_company_information",
            label: "Agregar información de la empresa",
            value: false,
            depend: "company_information_view_company_information",
        },
        {
            field: "company_information_edit_company_information",
            label: "Editar información de la empresa",
            value: false,
            depend: "company_information_view_company_information",
        },
        {
            field: "company_information_delete_company_information",
            label: "Eliminar información de la empresa",
            value: false,
            depend: "company_information_view_company_information",
        },
    ],

    message: [
        {
            field: "inbox_view_inbox",
            label: "Ver Modulo de mensajes",
            value: false,
        },
        {
            field: "inbox_view_inbox",
            label: "Ver Tabla de mensajes",
            value: false,
            depend: "inbox_view_inbox",
        },
        {
            field: "inbox_send_message",
            label: "Enviar Mensajes",
            value: false,
            depend: "inbox_view_inbox",
        },
    ],
    releases: [
        {
            field: "release_view_release",
            label: "Ver Actualizaciones",
            value: false,
        },
        {
            field: "release_add_release",
            label: "Agregar Actualizaciones",
            value: false,
            depend: "release_view_release",
        },
        {
            field: "release_edit_release",
            label: "Editar Actualizaciones",
            value: false,
            depend: "release_view_release",
        },
        {
            field: "release_add_description",
            label: "Agregar descripción de Actualizaciones",
            value: false,
            depend: "release_view_release",
        },
        {
            field: "release_edit_description",
            label: "Editar descripción de Actualizaciones",
            value: false,
            depend: "release_view_release",
        },
        {
            field: "release_delete_description",
            label: "Eliminar descripción de Actualizaciones",
            value: false,
            depend: "release_view_release",
        },
    ],
};

export const accordions = ref({
    dashboard: [
        {
            title: "Ver Dashboard",
            filter: "dashboard_view_dashboard",
        },
    ],
    plan: [
        {
            title: "Listado de planes de internet",
            filter: "plan_view_internet",
        },
        { title: "Listado de planes de voz", filter: "plan_view_voz" },
        {
            title: "Listado de planes personalizados",
            filter: "plan_view_custom",
        },
        { title: "Listado de planes paquetes", filter: "plan_view_package" },
    ],
    crm: [
        { title: "Clientes potenciales", filter: "crm_view_crm" },
        { title: "CRM Información", filter: "crm_information_view_tab_crm" },
        {
            title: "CRM Geolocalización",
            filter: "crm_information_geolocation_crm",
        },
        { title: "CRM Documentos", filter: "crm_document_view_tab_crm" },
    ],
    client: [
        { title: "Dashoboard", filter: "client_view_dashboard" },
        { title: "Clientes", filter: "client_view_client" },
        {
            title: "Geolocalización",
            filter: "client_information_geolocation_client",
        },
        { title: "Tabs", filter: "client_information_view_tab_client" },
        { title: "Servicio de internet", filter: "client_service_internet" },
        { title: "Servicio de voz", filter: "client_service_voz" },
        { title: "Servicio Custom", filter: "client_service_custom" },
        { title: "Paquetes", filter: "client_service_bundle" },
        { title: "Facturación", filter: "client_billing_view_tab_client" },
        { title: "Documentos", filter: "client_document_view_tab_client" },
    ],
    seller: [
        { title: "Dashboard", filter: "seller_view_dashboard" },
        {
            title: "Listado de vendedores",
            filter: "seller_view_seller",
        },
        {
            title: "Panel de información del vendedor",
            filter: "seller_view_panel",
        },
        {
            title: "Ver Prospectos",
            filter: "seller_view_prospects",
        },
        {
            title: "Ver Listado De Ventas",
            filter: "seller_view_sales",
        },
        {
            title: "Facturación",
            filter: "seller_view_billing",
        },
        {
            title: "Corte mostrador",
            filter: "seller_cuts",
        },
        {
            title: "Articulos",
            filter: "selleritems_view_selleritems",
        },
    ],
    ticket: [
        {
            title: "Dashboard",
            filter: "ticket_view_dashboard",
        },
        {
            title: "Listado de tickets nuevos/abiertos",
            filter: "ticket_view_open",
        },
        {
            title: "Listado de tickets cerrados",
            filter: "ticket_view_close",
        },
        {
            title: "Listado de tickets reciclados",
            filter: "ticket_view_recycling",
        },
    ],
    finance: [
        {
            title: "Listado de transacciones",
            filter: "finance_view_transactions",
        },
        {
            title: "Listado de facturacion",
            filter: "finance_view_billing",
        },
        {
            title: "Listado de pagos",
            filter: "finance_view_payments",
        },
        {
            title: "Listado de Facturas",
            filter: "finance_view_invoices",
        },
        {
            title: "Contabilidad General",
            filter: "finance_view_general_accounting",
        },
    ],
    message: [
        {
            title: "Mensajes",
            filter: "inbox_view_inbox",
        },
    ],
    maps: [
        {
            title: "Listado de mapas",
            filter: "maps_view_maps",
        },
    ],
    olts: [
        {
            title: "OLTs",
            filter: "olt_view",
        },
    ],
    scheduling: [
        {
            title: "Ver Tareas en barra lateral",
            filter: "scheduling_view_scheduling",
        },
        {
            title: "Proyectos",
            filter: "scheduling_project_view_project",
        },
        {
            title: "Tareas",
            filter: "task_view_task",
        },
        {
            title: "Calendario",
            filter: "scheduling_view_calendar",
        },
    ],
    network: [
        {
            title: "Enrutadores",
            filter: "router_view_router",
        },
        {
            title: "Redes Ipv4",
            filter: "ipv4_view_ipv4",
        },
    ],
    inventory: [
        {
            title: "Inventario",
            filter: "inventory_view_inventory",
        },
        {
            title: "Almacen",
            filter: "inventory_store_view_inventory_store",
        },
    ],
    administration: [
        {
            title: "Administración",
            filter: "admin_view_module",
        },
        {
            title: "Estado",
            filter: "state_view_state",
        },
        {
            title: "Municipio",
            filter: "municipality_view_municipality",
        },
        {
            title: "Colonia",
            filter: "colony_view_colony",
        },
        {
            title: "Método de Pago",
            filter: "method_payment_view_method_payment",
        },
        {
            title: "IFT",
            filter: "ift_view_ift",
        },
        // ------------------------------
        {
            title: "Meganet",
            filter: "admin_view_meganet",
        },
        {
            title: "Registros",
            filter: "admin_view_registers",
        },
        {
            title: "Información",
            filter: "admin_view_information",
        },
        {
            title: "Informes",
            filter: "admin_view_reports",
        },
        {
            title: "Scripts Ejecutables",
            filter: "admin_view_scripts",
        },
        {
            title: "Roles",
            filter: "role_view_role",
        },
        {
            title: "Usuarios",
            filter: "user_view_user",
        },
        {
            title: "Documentación",
            filter: "documentation_view_documentation",
        },
        {
            title: "Sucursales",
            filter: "view_sucursal",
        },
    ],
    configuration: [
        {
            title: "Configuración",
            filter: "config_view_module",
        },
        {
            title: "Sistema",
            filter: "config_view_system",
        },
        {
            title: "Principal",
            filter: "config_view_main",
        },
        {
            title: "Finanzas",
            filter: "config_view_finance",
        },
        {
            title: "Gestión de red",
            filter: "config_view_network_management",
        },
        {
            title: "Helpdesk",
            filter: "config_view_helpdesk",
        },
        {
            title: "Scheduling",
            filter: "config_view_scheduling",
        },
        {
            title: "Clientes potenciales",
            filter: "config_view_potencial_customer",
        },
        {
            title: "Inventario",
            filter: "config_view_inventory",
        },
        {
            title: "Integraciones",
            filter: "config_view_integrations",
        },
        {
            title: "Voz",
            filter: "config_view_voice",
        },
        {
            title: "Herramientas",
            filter: "config_view_tools",
        },
        {
            title: "Ventas",
            filter: "config_view_sales",
        },
        {
            title: "Google Maps",
            filter: "config_view_google_maps",
        },
    ],
    releases: [
        {
            title: "Actualizaciones",
            filter: "release_view_release",
        },
    ],
});
