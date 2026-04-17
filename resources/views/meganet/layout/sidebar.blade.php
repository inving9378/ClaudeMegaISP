<div class="vertical-menu">

    <div data-simplebar class="h-100">

        <!--- Sidemenu -->
        <div id="sidebar-menu">
            <!-- Left Menu Start -->
            <ul class="metismenu list-unstyled" id="side-menu">
                <li class="menu-title" data-key="t-menu">Menu</li>
                @can('dashboard_view_dashboard')
                    <li>
                        <a href="{{ url('/') }}">
                            <i data-feather="home"></i>
                            <span data-key="t-dashboard">Dashboard</span>
                        </a>
                    </li>
                @endcan

                @can('release_view_release')
                    <li>
                        <a href="{{ url('/releases') }}">
                            <i data-feather="package"></i>
                            <span data-key="t-dashboard">Actualizaciones</span>
                        </a>
                    </li>
                @endcan

                <li>
                    @canany(['plan_view_internet', 'plan_view_voz', 'plan_view_custom', 'plan_view_package'])
                        <a href="javascript: void(0);" class="has-arrow">
                            <i data-feather="grid"></i>
                            <span data-key="t-apps">Planes</span>
                        </a>
                    @endcanany
                    <ul class="sub-menu" aria-expanded="false">
                        @can('plan_view_internet')
                            <li>
                                <a href="{{ url('internet') }}">
                                    <span data-key="t-internet"><small><i class="fa fa-fw fa-wifi"></i></small>
                                        Internet</span>
                                </a>
                            </li>
                        @endcan
                        @can('plan_view_voz')
                            <li>
                                <a href="{{ url('voz') }}">
                                    <span data-key="t-voz"><small><i class="fa fa-fw fa-phone"></i></small> Voz</span>
                                </a>
                            </li>
                        @endcan
                        @can('plan_view_custom')
                            <li>
                                <a href="{{ url('custom') }}">
                                    <span data-key="t-custom"><small><i class="fa fa-fw fa-sitemap"></i></small>
                                        Personalizado</span>
                                </a>
                            </li>
                        @endcan
                        @can('plan_view_package')
                            <li>
                                <a href="{{ url('paquetes') }}">
                                    <span data-key="t-paquetes"><small><i
                                                class="fa fa-fw fa-object-group"></i></small>Paquetes</span>
                                </a>
                            </li>
                        @endcan
                    </ul>
                </li>

                @can('crm_view_crm')
                    <li>
                        <a href="javascript: void(0);" class="has-arrow">
                            <i data-feather="user-x"></i>
                            <span data-key="t-crm">Clientes pontenciales</span>
                        </a>
                        <ul class="sub-menu" aria-expanded="false">
                            @can('crm_add_crm')
                                <li>
                                    <a href="{{ url('/crm/crear') }}">
                                        <span data-key="t-crm-crear"><small><i class="fa fa-fw fa-user"></i></small>
                                            Crear</span>
                                    </a>
                                </li>
                            @endcan
                            @can('crm_view_crm')
                                <li>
                                    <a href="{{ url('/crm/listar') }}">
                                        <span data-key="t-crm-listar"><small><i class="fa fa-fw fa-list"></i></small>
                                            Listar</span>
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcan
                <li>
                    @canany(['client_view_dashboard', 'client_view_client', 'client_add_client'])
                        <a href="javascript: void(0);" class="has-arrow">
                            <i data-feather="user-check"></i>
                            <span data-key="t-cliente">Cliente</span>
                        </a>
                    @endcan
                    <ul class="sub-menu" aria-expanded="false">
                        @can('client_view_dashboard')
                            <li>
                                <a href="{{ url('/cliente/') }}">
                                    <span data-key="t-cliente-dashboard">
                                        <small>
                                            <i class="fas fa-table"></i>
                                        </small>
                                        Dashboard
                                    </span>
                                </a>
                            </li>
                        @endcan
                        @can('client_add_client')
                            <li>
                                <a href="{{ url('/cliente/crear') }}">
                                    <span data-key="t-cliente-crear"><small><i class="fa fa-fw fa-user"></i></small>
                                        Crear</span>
                                </a>
                            </li>
                        @endcan
                        @can('client_view_client')
                            <li>
                                <a href="{{ url('/cliente/listar') }}">
                                    <span data-key="t-cliente-listar"><small><i class="fa fa-fw fa-list"></i></small>
                                        Listar</span>
                                </a>
                            </li>
                        @endcan
                    </ul>
                </li>

                <li>
                    @canany(['seller_view_dashboard', 'seller_view_seller', 'seller_view_panel'])
                        <a href="javascript: void(0);" class="has-arrow">
                            <i data-feather="users"></i>
                            <span data-key="t-vendedores">Vendedores</span>
                        </a>
                    @endcan
                    <ul class="sub-menu" aria-expanded="false">
                        @can('seller_view_dashboard')
                            <li>
                                <a href="{{ url('/vendedores/dashboard') }}">
                                    <span data-key="t-vendedores-crear"><small>
                                            <i class="fas fa-chart-bar"></i>
                                        </small>Dashboard
                                    </span>
                                </a>
                            </li>
                        @endcan
                        <li>
                            @can('seller_view_seller')
                                <a href="{{ url('/sellers/seller') }}">
                                    <span data-key="t-vendedores-crear"><small>
                                            <i class="fas fa-users"></i>
                                        </small>Lista de vendedores
                                    </span>
                                </a>
                            @endcan
                        </li>
                        <li>
                            @can('seller_view_panel')
                                <a href="{{ url('/vendedores/seguimiento-me') }}">
                                    <span data-key="t-vendedores-crear"><small>
                                            <i class="far fa-id-card"></i>
                                        </small>Mi panel
                                    </span>
                                </a>
                            @endcan
                        </li>
                    </ul>
                </li>


                <li>
                    @canany(['ticket_view_dashboard', 'ticket_view_open', 'ticket_view_close', 'ticket_view_recycling'])
                        <a href="javascript: void(0);" class="has-arrow">
                            <i data-feather="grid"></i>
                            <span data-key="t-cliente">Ticket</span>
                        </a>
                    @endcan
                    <ul class="sub-menu" aria-expanded="false">
                        @can('ticket_view_dashboard')
                            <li>
                                <a href="{{ url('/tickets/') }}">
                                    <span data-key="t-ticket-dashboard">
                                        <small>
                                            <i class="fas fa-table"></i>
                                        </small>
                                        Dashboard
                                    </span>
                                </a>
                            </li>
                        @endcan
                        @can('ticket_view_open')
                            <li>
                                <a href="{{ url('/tickets/abiertos') }}">
                                    <span data-key="t-ticket-abierto">
                                        <small>
                                            <i class="fas fa-ticket-alt"></i>
                                        </small>
                                        Listar
                                        nuevo/abierto
                                    </span>
                                </a>
                            </li>
                        @endcan
                        @can('ticket_view_close')
                            <li>
                                <a href="{{ url('/tickets/cerrados') }}">
                                    <span data-key="t-ticket-cerrado">
                                        <small>
                                            <i class="fas fa-check-circle"></i>
                                        </small>
                                        Listar cerrados
                                    </span>
                                </a>
                            </li>
                        @endcan
                        @can('ticket_view_recycling')
                            <li>
                                <a href="{{ url('/tickets/reciclados') }}">
                                    <span data-key="t-ticket-reciclaje">
                                        <small>
                                            <i class="fas fa-trash"></i>
                                        </small>Listar reciclados
                                    </span>
                                </a>
                            </li>
                        @endcan
                    </ul>
                </li>

                <li>
                    @canany(['finance_view_transactions', 'finance_view_billing', 'finance_view_payments'])
                        <a href="javascript: void(0);" class="has-arrow">
                            <i data-feather="grid"></i>
                            <span data-key="t-cliente">Finanzas</span>
                        </a>
                    @endcan
                    <ul class="sub-menu" aria-expanded="false">
                        @can('finance_view_transactions')
                            <li>
                                <a href="{{ url('/finanzas/transacciones') }}">
                                    <span data-key="t-ticket-dashboard">
                                        <small>
                                            <i class="fas fa-hand-holding-usd"></i>
                                        </small>
                                        Transacciones
                                    </span>
                                </a>
                            </li>
                        @endcan
                        @can('finance_view_billing')
                            <li>
                                <a href="{{ url('/finanzas/facturas') }}">
                                    <span data-key="t-ticket-abierto">
                                        <small>
                                            <i class="fas fa-file-invoice-dollar"></i>
                                        </small>
                                        Facturas
                                    </span>
                                </a>
                            </li>
                        @endcan
                        @can('finance_view_payments')
                            <li>
                                <a href="{{ url('/finanzas/pagos') }}">
                                    <span data-key="t-ticket-cerrado">
                                        <small>
                                            <i class="fas fa-credit-card"></i>
                                        </small>
                                        Pagos
                                    </span>
                                </a>
                            </li>
                        @endcan

                        @can('finance_view_invoices')
                            <li>
                                <a href="{{ url('/finanzas/invoices') }}">
                                    <span data-key="t-invoice-proforma">
                                        <small>
                                            <i class="fas fa-file-invoice-dollar"></i>
                                        </small>
                                        Facturas Proforma
                                    </span>
                                </a>
                            </li>
                        @endcan
                        @can('finance_view_general_accounting')
                            <li>
                                <a href="{{ url('/finanzas/general-accounting') }}">
                                    <span data-key="t-invoice-proforma">
                                        <small>
                                            <i class="fas fa-file-invoice-dollar"></i>
                                        </small>
                                        Contabilidad General
                                    </span>
                                </a>
                            </li>
                        @endcan
                    </ul>
                </li>
                @can('inbox_view_inbox')
                    <li>
                        <a href="javascript: void(0);" class="has-arrow">
                            <i data-feather="mail"></i>
                            <span data-key="t-cliente">Mensajes</span>
                        </a>
                        <ul class="sub-menu" aria-expanded="false">
                            <li>
                                <a href="{{ url('/message/inbox') }}">
                                    <span data-key="t-ticket-dashboard">
                                        <small>
                                            <i class="fas fa-inbox"></i> <!-- Cambiado aquí -->
                                        </small>
                                        Inbox
                                    </span>
                                </a>
                            </li>
                        </ul>
                    </li>
                @endcan

                @can('scheduling_view_scheduling')
                    <li>
                        <a href="javascript: void(0);" class="has-arrow">
                            <i data-feather="calendar"></i>
                            <span data-key="t-cliente">Tareas Programadas</span>
                        </a>
                        <ul class="sub-menu" aria-expanded="false">
                            @can('scheduling_project_view_project')
                                <li>
                                    <a href="{{ url('/scheduling/project') }}">
                                        <span data-key="t-ticket-dashboard">
                                            <small>
                                                <i class="fas fa-project-diagram"></i>
                                            </small>
                                            Proyectos
                                        </span>
                                    </a>
                                </li>
                            @endcan

                            @can('task_view_task')
                                <li>
                                    <a href="{{ url('/scheduling/task') }}">
                                        <span data-key="t-ticket-dashboard">
                                            <small>
                                                <i class="fas fa-tasks"></i>
                                            </small>
                                            Tareas
                                        </span>
                                    </a>
                                </li>
                            @endcan

                            @can('scheduling_view_calendar')
                                <li>
                                    <a href="{{ url('/scheduling/task/calendar') }}">
                                        <span data-key="t-ticket-dashboard">
                                            <small>
                                                <i class="fas fa-calendar"></i>
                                            </small>
                                            Calendario
                                        </span>
                                    </a>
                                </li>
                            @endcan


                            @can('task_view_archived_task')
                                <li>
                                    <a href="{{ url('/scheduling/task/show-archived') }}">
                                        <span data-key="t-ticket-dashboard">
                                            <small>
                                                <i class="fas fa-archive"></i>
                                            </small>
                                            Archivados
                                        </span>
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                @canany(['maps_view_maps'])
                    <li>

                        <a href="{{ url('/mapas/') }}">
                            <i data-feather="map"></i>
                            <span data-key="t-cliente">Mapas</span>
                        </a>

                    </li>
                @endcan

                @canany(['olt_view'])
                    <li>
                        <a href="/olts">
                            <i data-feather="server"></i>
                            <span data-key="t-olts">OLTs</span>
                        </a>
                    </li>
                @endcan

                <li>
                    @canany(['router_view_router', 'ipv4_view_ipv4', 'router_add_router', 'ipv4_add_ipv4'])
                        <a href="javascript: void(0);" class="has-arrow">
                            <i data-feather="box"></i>
                            <span data-key="t-gestion-red">Gestión de red</span>
                        </a>
                    @endcan
                    <ul class="sub-menu" aria-expanded="false">
                        <li>
                            @canany(['router_add_router', 'router_view_router'])
                                <a href="javascript: void(0);" class="has-arrow">
                                    <i data-feather="box"></i>
                                    <span data-key="t-router">Enrutadores</span>
                                </a>
                            @endcan
                            <ul class="sub-menu" aria-expanded="false">
                                @can('router_add_router')
                                    <li>
                                        <a href="{{ url('/red/router/crear') }}">
                                            <span data-key="t-router-crear"><small><i
                                                        class="fa fa-fw fa-puzzle-piece"></i></small> Add</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('router_view_router')
                                    <li>
                                        <a href="{{ url('/red/router/listar') }}">
                                            <span data-key="t-router-listar"><small><i
                                                        class="fa fa-fw fa-list"></i></small> Listar</span>
                                        </a>
                                    </li>
                                @endcan
                            </ul>
                        </li>
                        <li>
                            @canany(['ipv4_add_ipv4', 'ipv4_view_ipv4'])
                                <a href="javascript: void(0);" class="has-arrow">
                                    <i data-feather="box"></i>
                                    <span data-key="t-ipv4">Redes Ipv4</span>
                                </a>
                            @endcan
                            <ul class="sub-menu" aria-expanded="false">
                                @can('ipv4_add_ipv4')
                                    <li>
                                        <a href="{{ url('/red/ipv4/crear') }}">
                                            <span data-key="t-ipv4-crear"><small><i
                                                        class="fa fa-fw fa-puzzle-piece"></i></small> Add</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('ipv4_view_ipv4')
                                    <li>
                                        <a href="{{ url('/red/ipv4/listar') }}">
                                            <span data-key="t-ipv4-listar"><small><i class="fa fa-fw fa-list"></i></small>
                                                Listar</span>
                                        </a>
                                    </li>
                                @endcan
                            </ul>

                        </li>
                    </ul>
                </li>

                <li>
                    @canany(['inventory_view_inventory', 'inventory_item_view_inventory_item',
                        'inventory_item_type_view_inventory_item_type', 'inventory_movement_view_inventory_movement',
                        'inventory_store_view_inventory_store',
                        'inventory_item_custom_model_view_inventory_item_custom_model'])
                        <a href="javascript: void(0);" class="has-arrow">
                            <i data-feather="archive"></i>
                            <span data-key="t-gestion-red">Inventario</span>
                        </a>
                    @endcan
                    <ul class="sub-menu" aria-expanded="false">
                        <li>
                            @canany(['inventory_store_view_inventory_store'])
                                <a href="javascript: void(0);" class="has-arrow">
                                    <i data-feather="layers"></i>
                                    <span data-key="t-router">Almacenes</span>
                                </a>
                            @endcan
                            <ul class="sub-menu" aria-expanded="false">
                                @can('inventory_store_view_inventory_store')
                                    <li>
                                        <a href="{{ url('/inventory/inventory_store') }}">
                                            <span data-key="t-router-listar"><small><i
                                                        class="fa fa-fw fa-list"></i></small> Listar</span>
                                        </a>
                                    </li>
                                @endcan
                            </ul>
                        </li>
                        <li>
                            @canany(['inventory_item_view_inventory_item'])
                                <a href="javascript: void(0);" class="has-arrow">
                                    <i data-feather="layers"></i>
                                    <span data-key="t-router">Tipo de Artículos</span>
                                </a>
                            @endcan
                            <ul class="sub-menu" aria-expanded="false">
                                @can('inventory_item_view_inventory_item')
                                    <li>
                                        <a href="{{ url('/inventory/inventory_item_type') }}">
                                            <span data-key="t-router-listar"><small><i
                                                        class="fa fa-fw fa-list"></i></small> Listar</span>
                                        </a>
                                    </li>
                                @endcan
                            </ul>
                        </li>
                        <li>
                            @canany(['inventory_item_view_inventory_item',
                                'inventory_item_custom_model_view_inventory_item_custom_model'])
                                <a href="javascript: void(0);" class="has-arrow">
                                    <i data-feather="package"></i>
                                    <span data-key="t-router">Artículos</span>
                                </a>
                            @endcan
                            <ul class="sub-menu" aria-expanded="false">
                                @can('inventory_item_view_inventory_item')
                                    <li>
                                        <a href="{{ url('/inventory/inventory_item_stock') }}">
                                            <span data-key="t-router-listar"><small><i
                                                        class="fa fa-fw fa-list"></i></small> Listar</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('inventory_item_custom_model_view_inventory_item_custom_model')
                                    <li>
                                        <a href="{{ url('/inventory/inventory_item_custom_model') }}">
                                            <span data-key="t-router-listar"><small><i
                                                        class="fa fa-fw fa-list"></i></small> Articulos Custom</span>
                                        </a>
                                    </li>
                                @endcan
                            </ul>
                        </li>
                        <li>
                            @canany(['inventory_movement_view_inventory_movement'])
                                <a href="javascript: void(0);" class="has-arrow">
                                    <i data-feather="shuffle"></i>
                                    <span data-key="t-router">Movimientos</span>
                                </a>
                            @endcan
                            <ul class="sub-menu" aria-expanded="false">
                                @can('inventory_movement_view_inventory_movement')
                                    <li>
                                        <a href="{{ url('/inventory/inventory_movement') }}">
                                            <span data-key="t-router-listar"><small><i
                                                        class="fa fa-fw fa-list"></i></small> Listar</span>
                                        </a>
                                    </li>
                                @endcan
                            </ul>
                        </li>
                    </ul>
                </li>
                <li>
                    @can(['admin_view_module'])
                        <a href="{{ url('/administracion') }}" class="has-arrow">
                            <i data-feather="command"></i>
                            <span data-key="t-administracion">Administración</span>
                        </a>
                    @endcan
                </li>

                <li>
                    @can(['config_view_module'])
                        <a href="{{ url('/configuracion') }}" class="has-arrow">
                            <i data-feather="tool"></i>
                            <span data-key="t-configuracion">
                                Configuración</span>
                        </a>
                    @endcan
                </li>
            </ul>
        </div>
        <!-- Sidebar -->
    </div>
</div>
