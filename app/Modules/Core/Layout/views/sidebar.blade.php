<div class="vertical-menu">

    <div data-simplebar class="h-100">

        <!--- Sidemenu -->
        <div id="sidebar-menu">
            <ul class="metismenu list-unstyled" id="side-menu">
                <li class="menu-title" data-key="t-menu">Menu</li>

                {{-- 1. Dashboard --}}
                @can('dashboard_view_dashboard')
                    <li>
                        <a href="{{ url('/') }}">
                            <i data-feather="home"></i>
                            <span data-key="t-dashboard">Dashboard</span>
                        </a>
                    </li>
                @endcan

                {{-- 2. Planes --}}
                <li>
                    @canany(['plan_view_internet', 'plan_view_voz', 'plan_view_custom', 'plan_view_package'])
                        <a href="javascript: void(0);" class="has-arrow">
                            <i data-feather="grid"></i>
                            <span data-key="t-planes">Planes</span>
                        </a>
                    @endcanany
                    <ul class="sub-menu" aria-expanded="false">
                        @can('plan_view_internet')
                            <li>
                                <a href="{{ url('/internet') }}">
                                    <span data-key="t-internet"><small><i class="fa fa-fw fa-wifi"></i></small> Internet</span>
                                </a>
                            </li>
                        @endcan
                        @can('plan_view_voz')
                            <li>
                                <a href="{{ url('/voz') }}">
                                    <span data-key="t-voz"><small><i class="fa fa-fw fa-phone"></i></small> Voz</span>
                                </a>
                            </li>
                        @endcan
                        @can('plan_view_custom')
                            <li>
                                <a href="{{ url('/custom') }}">
                                    <span data-key="t-custom"><small><i class="fa fa-fw fa-sitemap"></i></small> Personalizado</span>
                                </a>
                            </li>
                        @endcan
                        @can('plan_view_package')
                            <li>
                                <a href="{{ url('/paquetes') }}">
                                    <span data-key="t-paquetes"><small><i class="fa fa-fw fa-object-group"></i></small> Paquetes</span>
                                </a>
                            </li>
                        @endcan
                    </ul>
                </li>

                {{-- 3. Clientes potenciales (CRM) --}}
                @can('crm_view_crm')
                    <li>
                        <a href="javascript: void(0);" class="has-arrow">
                            <i data-feather="user-x"></i>
                            <span data-key="t-crm">Clientes potenciales</span>
                        </a>
                        <ul class="sub-menu" aria-expanded="false">
                            @can('crm_add_crm')
                                <li>
                                    <a href="{{ url('/crm/crear') }}">
                                        <span data-key="t-crm-crear"><small><i class="fa fa-fw fa-user"></i></small> Crear</span>
                                    </a>
                                </li>
                            @endcan
                            @can('crm_view_crm')
                                <li>
                                    <a href="{{ url('/crm/listar') }}">
                                        <span data-key="t-crm-listar"><small><i class="fa fa-fw fa-list"></i></small> Listar</span>
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                {{-- 4. Clientes --}}
                <li>
                    @canany(['client_view_dashboard', 'client_view_client', 'client_add_client'])
                        <a href="javascript: void(0);" class="has-arrow">
                            <i data-feather="user-check"></i>
                            <span data-key="t-cliente">Clientes</span>
                        </a>
                    @endcanany
                    <ul class="sub-menu" aria-expanded="false">
                        @can('client_view_dashboard')
                            <li>
                                <a href="{{ url('/cliente/') }}">
                                    <span data-key="t-cliente-dashboard"><small><i class="fas fa-table"></i></small> Dashboard</span>
                                </a>
                            </li>
                        @endcan
                        @can('client_add_client')
                            <li>
                                <a href="{{ url('/cliente/crear') }}">
                                    <span data-key="t-cliente-crear"><small><i class="fa fa-fw fa-user"></i></small> Crear</span>
                                </a>
                            </li>
                        @endcan
                        @can('client_view_client')
                            <li>
                                <a href="{{ url('/cliente/listar') }}">
                                    <span data-key="t-cliente-listar"><small><i class="fa fa-fw fa-list"></i></small> Listar</span>
                                </a>
                            </li>
                        @endcan
                    </ul>
                </li>

                {{-- 5. Gestión de red (sin OLTs — son item primario en posición 8) --}}
                <li>
                    @canany(['router_view_router', 'ipv4_view_ipv4', 'router_add_router', 'ipv4_add_ipv4'])
                        <a href="javascript: void(0);" class="has-arrow">
                            <i data-feather="box"></i>
                            <span data-key="t-gestion-red">Gestión de red</span>
                        </a>
                    @endcanany
                    <ul class="sub-menu" aria-expanded="false">
                        <li>
                            @canany(['router_add_router', 'router_view_router'])
                                <a href="javascript: void(0);" class="has-arrow">
                                    <i data-feather="box"></i>
                                    <span data-key="t-router">Enrutadores</span>
                                </a>
                            @endcanany
                            <ul class="sub-menu" aria-expanded="false">
                                @can('router_add_router')
                                    <li>
                                        <a href="{{ url('/red/router/crear') }}">
                                            <span data-key="t-router-crear"><small><i class="fa fa-fw fa-puzzle-piece"></i></small> Add</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('router_view_router')
                                    <li>
                                        <a href="{{ url('/red/router/listar') }}">
                                            <span data-key="t-router-listar"><small><i class="fa fa-fw fa-list"></i></small> Listar</span>
                                        </a>
                                    </li>
                                @endcan
                            </ul>
                        </li>
                        <li>
                            @canany(['ipv4_add_ipv4', 'ipv4_view_ipv4'])
                                <a href="javascript: void(0);" class="has-arrow">
                                    <i data-feather="box"></i>
                                    <span data-key="t-ipv4">Redes IPv4</span>
                                </a>
                            @endcanany
                            <ul class="sub-menu" aria-expanded="false">
                                @can('ipv4_add_ipv4')
                                    <li>
                                        <a href="{{ url('/red/ipv4/crear') }}">
                                            <span data-key="t-ipv4-crear"><small><i class="fa fa-fw fa-puzzle-piece"></i></small> Add</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('ipv4_view_ipv4')
                                    <li>
                                        <a href="{{ url('/red/ipv4/listar') }}">
                                            <span data-key="t-ipv4-listar"><small><i class="fa fa-fw fa-list"></i></small> Listar</span>
                                        </a>
                                    </li>
                                @endcan
                            </ul>
                        </li>
                    </ul>
                </li>

                {{--
                    6. Finanzas — incluye Marketing como sub-sección (addon-marketing,
                    sidebar.location=submenu, sidebar.parent=finanzas).
                    $sidebarSubmenu viene de SidebarComposer → ModuleRegistry::getSubmenuItemsFor('finanzas').
                    El bloque de Marketing hardcodeado actúa como fallback y fuente de verdad de permisos
                    hasta que el módulo exporte exactamente los mismos children en su module.json.
                --}}
                <li>
                    @canany(['finance_view_transactions', 'finance_view_billing', 'finance_view_payments',
                             'finance_view_invoices', 'finance_view_general_accounting', 'payments_manage_providers',
                             'view-marketing-leads', 'marketing_leads_view', 'view-conversations',
                             'view-marketing-forms', 'manage-marketing-forms', 'marketing_campaigns_view',
                             'marketing_templates_manage', 'view-video-templates', 'generate-video-content',
                             'create-marketing-campaigns', 'configure-brand-kit', 'test-voices',
                             'view-publishing-dashboard', 'publish-content', 'manage-publication-queue',
                             'manage-publishing-channels'])
                        <a href="javascript: void(0);" class="has-arrow">
                            <i data-feather="grid"></i>
                            <span data-key="t-finanzas">Finanzas</span>
                        </a>
                    @endcanany
                    <ul class="sub-menu" aria-expanded="false">

                        {{-- Finanzas core --}}
                        @can('finance_view_transactions')
                            <li>
                                <a href="{{ url('/finanzas/transacciones') }}">
                                    <span data-key="t-finanzas-tx"><small><i class="fas fa-hand-holding-usd"></i></small> Transacciones</span>
                                </a>
                            </li>
                        @endcan
                        @can('finance_view_billing')
                            <li>
                                <a href="{{ url('/finanzas/facturas') }}">
                                    <span data-key="t-finanzas-fact"><small><i class="fas fa-file-invoice-dollar"></i></small> Facturas</span>
                                </a>
                            </li>
                        @endcan
                        @can('finance_view_payments')
                            <li>
                                <a href="{{ url('/finanzas/pagos') }}">
                                    <span data-key="t-finanzas-pagos"><small><i class="fas fa-credit-card"></i></small> Pagos</span>
                                </a>
                            </li>
                        @endcan
                        @can('finance_view_invoices')
                            <li>
                                <a href="{{ url('/finanzas/invoices') }}">
                                    <span data-key="t-finanzas-proforma"><small><i class="fas fa-file-invoice-dollar"></i></small> Facturas Proforma</span>
                                </a>
                            </li>
                        @endcan
                        @can('finance_view_general_accounting')
                            <li>
                                <a href="{{ url('/finanzas/general-accounting') }}">
                                    <span data-key="t-finanzas-cont"><small><i class="fas fa-file-invoice-dollar"></i></small> Contabilidad General</span>
                                </a>
                            </li>
                        @endcan
                        @can('payments_manage_providers')
                            <li>
                                <a href="{{ url('/finanzas/metodos-pago') }}">
                                    <span data-key="t-finanzas-spei"><small><i class="fas fa-university"></i></small> Métodos de Pago SPEI</span>
                                </a>
                            </li>
                        @endcan

                        {{-- Marketing — location:submenu, parent:finanzas (addon-marketing) --}}
                        @canany(['view-marketing-leads', 'marketing_leads_view', 'view-conversations',
                                 'view-marketing-forms', 'manage-marketing-forms', 'marketing_campaigns_view',
                                 'marketing_templates_manage', 'view-video-templates', 'generate-video-content',
                                 'create-marketing-campaigns', 'configure-brand-kit', 'test-voices',
                                 'view-publishing-dashboard', 'publish-content', 'manage-publication-queue',
                                 'manage-publishing-channels'])
                            <li class="sidebar-section-divider">
                                <span class="sidebar-section-label">Marketing</span>
                            </li>
                            @canany(['view-marketing-leads', 'marketing_leads_view'])
                                <li>
                                    <a href="{{ url('/marketing/leads') }}">
                                        <span data-key="t-mkt-leads"><small><i class="fa fa-fw fa-user-tag"></i></small> Leads</span>
                                    </a>
                                </li>
                            @endcanany
                            @can('view-conversations')
                                <li>
                                    <a href="{{ url('/marketing/conversations') }}">
                                        <span data-key="t-mkt-conv"><small><i class="fa fa-fw fa-comments"></i></small> Conversaciones</span>
                                    </a>
                                </li>
                            @endcan
                            @canany(['view-marketing-forms', 'manage-marketing-forms'])
                                <li>
                                    <a href="{{ url('/marketing/lead-forms') }}">
                                        <span data-key="t-mkt-forms"><small><i class="fa fa-fw fa-wpforms"></i></small> Formularios</span>
                                    </a>
                                </li>
                            @endcanany
                            @can('marketing_campaigns_view')
                                <li>
                                    <a href="{{ url('/marketing') }}">
                                        <span data-key="t-mkt-camp"><small><i class="fa fa-fw fa-bullhorn"></i></small> Campañas</span>
                                    </a>
                                </li>
                            @endcan
                            @can('marketing_templates_manage')
                                <li>
                                    <a href="{{ url('/marketing') }}?tab=templates">
                                        <span data-key="t-mkt-tpl"><small><i class="fa fa-fw fa-file-alt"></i></small> Plantillas</span>
                                    </a>
                                </li>
                            @endcan
                            @canany(['view-video-templates', 'generate-video-content'])
                                <li>
                                    <a href="{{ url('/marketing/video-templates') }}">
                                        <span data-key="t-mkt-video"><small><i class="fa fa-fw fa-video"></i></small> Video</span>
                                    </a>
                                </li>
                            @endcanany
                            @can('create-marketing-campaigns')
                                <li>
                                    <a href="{{ url('/marketing/campaigns/generate') }}">
                                        <span data-key="t-mkt-ia"><small><i class="fa fa-fw fa-magic"></i></small> Campaña IA</span>
                                    </a>
                                </li>
                            @endcan
                            @can('configure-brand-kit')
                                <li>
                                    <a href="{{ url('/marketing/brand-kit') }}">
                                        <span data-key="t-mkt-bk"><small><i class="fa fa-fw fa-palette"></i></small> Brand Kit</span>
                                    </a>
                                </li>
                            @endcan
                            @can('test-voices')
                                <li>
                                    <a href="{{ url('/marketing/voice-comparator') }}">
                                        <span data-key="t-mkt-vc"><small><i class="fa fa-fw fa-microphone"></i></small> Voces TTS</span>
                                    </a>
                                </li>
                            @endcan
                            @can('view-publishing-dashboard')
                                <li>
                                    <a href="{{ url('/marketing/publishing') }}">
                                        <span data-key="t-mkt-pub"><small><i class="fa fa-fw fa-broadcast-tower"></i></small> Publicador</span>
                                    </a>
                                </li>
                            @endcan
                            @can('publish-content')
                                <li>
                                    <a href="{{ url('/marketing/publishing/campaign') }}">
                                        <span data-key="t-mkt-pubcam"><small><i class="fa fa-fw fa-paper-plane"></i></small> Publicar</span>
                                    </a>
                                </li>
                            @endcan
                            @can('manage-publication-queue')
                                <li>
                                    <a href="{{ url('/marketing/publishing/queue') }}">
                                        <span data-key="t-mkt-queue"><small><i class="fa fa-fw fa-list-ol"></i></small> Cola</span>
                                    </a>
                                </li>
                            @endcan
                            @can('manage-publishing-channels')
                                <li>
                                    <a href="{{ url('/marketing/publishing/setup') }}">
                                        <span data-key="t-mkt-setup"><small><i class="fa fa-fw fa-plug"></i></small> Canales</span>
                                    </a>
                                </li>
                            @endcan
                        @endcanany

                    </ul>
                </li>

                {{-- 7. Inventario --}}
                <li>
                    @canany(['inventory_view_inventory', 'inventory_item_view_inventory_item',
                             'inventory_item_type_view_inventory_item_type', 'inventory_movement_view_inventory_movement',
                             'inventory_store_view_inventory_store', 'inventory_item_custom_model_view_inventory_item_custom_model',
                             'inventory_supplier_view_supplier', 'inventory_supplier_add_supplier',
                             'inventory_valuation_view_inventory_valuation'])
                        <a href="javascript: void(0);" class="has-arrow">
                            <i data-feather="archive"></i>
                            <span data-key="t-inventario">Inventario</span>
                        </a>
                    @endcanany
                    <ul class="sub-menu" aria-expanded="false">
                        <li>
                            @canany(['inventory_store_view_inventory_store'])
                                <a href="javascript: void(0);" class="has-arrow">
                                    <i data-feather="layers"></i>
                                    <span data-key="t-almacenes">Almacenes</span>
                                </a>
                            @endcanany
                            <ul class="sub-menu" aria-expanded="false">
                                @can('inventory_store_view_inventory_store')
                                    <li>
                                        <a href="{{ url('/inventory/inventory_store') }}">
                                            <span data-key="t-almacenes-listar"><small><i class="fa fa-fw fa-list"></i></small> Listar</span>
                                        </a>
                                    </li>
                                @endcan
                            </ul>
                        </li>
                        <li>
                            @canany(['inventory_item_view_inventory_item'])
                                <a href="javascript: void(0);" class="has-arrow">
                                    <i data-feather="layers"></i>
                                    <span data-key="t-tipos-art">Tipo de Artículos</span>
                                </a>
                            @endcanany
                            <ul class="sub-menu" aria-expanded="false">
                                @can('inventory_item_view_inventory_item')
                                    <li>
                                        <a href="{{ url('/inventory/inventory_item_type') }}">
                                            <span data-key="t-tipos-art-listar"><small><i class="fa fa-fw fa-list"></i></small> Listar</span>
                                        </a>
                                    </li>
                                @endcan
                            </ul>
                        </li>
                        <li>
                            @canany(['inventory_item_view_inventory_item', 'inventory_item_custom_model_view_inventory_item_custom_model'])
                                <a href="javascript: void(0);" class="has-arrow">
                                    <i data-feather="package"></i>
                                    <span data-key="t-articulos">Artículos</span>
                                </a>
                            @endcanany
                            <ul class="sub-menu" aria-expanded="false">
                                @can('inventory_item_view_inventory_item')
                                    <li>
                                        <a href="{{ url('/inventory/inventory_item_stock') }}">
                                            <span data-key="t-articulos-listar"><small><i class="fa fa-fw fa-list"></i></small> Listar</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('inventory_item_custom_model_view_inventory_item_custom_model')
                                    <li>
                                        <a href="{{ url('/inventory/inventory_item_custom_model') }}">
                                            <span data-key="t-articulos-custom"><small><i class="fa fa-fw fa-list"></i></small> Artículos Custom</span>
                                        </a>
                                    </li>
                                @endcan
                            </ul>
                        </li>
                        <li>
                            @canany(['inventory_movement_view_inventory_movement'])
                                <a href="javascript: void(0);" class="has-arrow">
                                    <i data-feather="shuffle"></i>
                                    <span data-key="t-movimientos">Movimientos</span>
                                </a>
                            @endcanany
                            <ul class="sub-menu" aria-expanded="false">
                                @can('inventory_movement_view_inventory_movement')
                                    <li>
                                        <a href="{{ url('/inventory/inventory_movement') }}">
                                            <span data-key="t-movimientos-listar"><small><i class="fa fa-fw fa-list"></i></small> Listar</span>
                                        </a>
                                    </li>
                                @endcan
                            </ul>
                        </li>
                        <li>
                            @canany(['inventory_supplier_view_supplier', 'inventory_supplier_add_supplier'])
                                <a href="javascript: void(0);" class="has-arrow">
                                    <i data-feather="truck"></i>
                                    <span data-key="t-proveedores">Proveedores</span>
                                </a>
                            @endcanany
                            <ul class="sub-menu" aria-expanded="false">
                                @can('inventory_supplier_add_supplier')
                                    <li>
                                        <a href="{{ url('/inventory/supplier/create') }}">
                                            <span><small><i class="fa fa-fw fa-plus"></i></small> Crear</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('inventory_supplier_view_supplier')
                                    <li>
                                        <a href="{{ url('/inventory/supplier') }}">
                                            <span data-key="t-proveedores-listar"><small><i class="fa fa-fw fa-list"></i></small> Listar</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('inventory_supplier_invoice_view_supplier_invoice')
                                    <li>
                                        <a href="javascript: void(0);" class="has-arrow">
                                            <i data-feather="file-text"></i>
                                            <span data-key="t-fact-prov">Facturas</span>
                                        </a>
                                        <ul class="sub-menu" aria-expanded="false">
                                            @can('inventory_supplier_invoice_add_supplier_invoice')
                                                <li>
                                                    <a href="{{ url('/inventory/supplier-invoice/create') }}">
                                                        <span><small><i class="fa fa-fw fa-plus"></i></small> Crear</span>
                                                    </a>
                                                </li>
                                            @endcan
                                            <li>
                                                <a href="{{ url('/inventory/supplier-invoice') }}">
                                                    <span><small><i class="fa fa-fw fa-list"></i></small> Listar</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </li>
                                @endcan
                            </ul>
                        </li>
                        @can('inventory_valuation_view_inventory_valuation')
                            <li>
                                <a href="{{ url('/inventory/inventory-valuation') }}">
                                    <i data-feather="bar-chart-2"></i>
                                    <span data-key="t-valuacion">Valuación Inventario</span>
                                </a>
                            </li>
                        @endcan
                    </ul>
                </li>

                {{-- 8. OLTs — standalone (extraído de Gestión de red) --}}
                @can('olt_view')
                    <li>
                        <a href="{{ url('/olts') }}">
                            <i data-feather="server"></i>
                            <span data-key="t-olts">OLTs</span>
                        </a>
                    </li>
                @endcan

                {{-- 9. Mapas --}}
                @can('maps_view_maps')
                    <li>
                        <a href="{{ url('/mapas/') }}">
                            <i data-feather="map"></i>
                            <span data-key="t-mapas">Mapas</span>
                        </a>
                    </li>
                @endcan

                {{-- 10. Cobranza --}}
                @canany(['cobranza.view', 'cobranza.configure'])
                    <li>
                        <a href="javascript: void(0);" class="has-arrow">
                            <i data-feather="phone-call"></i>
                            <span data-key="t-cobranza">Cobranza</span>
                        </a>
                        <ul class="sub-menu" aria-expanded="false">
                            @can('cobranza.view')
                                <li>
                                    <a href="{{ url('/cobranza/campanas') }}">
                                        <span><small><i class="fa fa-fw fa-broadcast-tower"></i></small> Campañas</span>
                                    </a>
                                </li>
                            @endcan
                            @can('cobranza.configure')
                                <li>
                                    <a href="{{ url('/cobranza/voip') }}">
                                        <span><small><i class="fa fa-fw fa-phone-square"></i></small> Config. VoIP</span>
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcanany

                {{-- 11. MegaFamilia --}}
                @hasanyrole('DESARROLLADOR|Administrador|Super Administrador|super-administrator|TECNICO')
                    @canany(['megafamilia_admin', 'megafamilia_support'])
                        <li>
                            <a href="javascript: void(0);" class="has-arrow">
                                <i data-feather="shield"></i>
                                <span data-key="t-megafamilia">MegaFamilia</span>
                            </a>
                            <ul class="sub-menu" aria-expanded="false">
                                @can('megafamilia_admin')
                                    <li><a href="{{ url('/megafamilia') }}"><span><small><i class="fa fa-fw fa-tachometer-alt"></i></small> Dashboard</span></a></li>
                                    <li><a href="{{ url('/megafamilia/clientes') }}"><span><small><i class="fa fa-fw fa-users"></i></small> Clientes</span></a></li>
                                    <li><a href="{{ url('/megafamilia/licencias') }}"><span><small><i class="fa fa-fw fa-key"></i></small> Licencias</span></a></li>
                                    <li><a href="{{ url('/megafamilia/planes') }}"><span><small><i class="fa fa-fw fa-layer-group"></i></small> Planes</span></a></li>
                                    <li><a href="{{ url('/megafamilia/perfiles') }}"><span><small><i class="fa fa-fw fa-child"></i></small> Perfiles</span></a></li>
                                @endcan
                                <li><a href="{{ url('/megafamilia/dispositivos') }}"><span><small><i class="fa fa-fw fa-mobile-screen"></i></small> Dispositivos</span></a></li>
                                <li><a href="{{ url('/megafamilia/solicitudes') }}"><span><small><i class="fa fa-fw fa-inbox"></i></small> Solicitudes</span></a></li>
                                <li><a href="{{ url('/megafamilia/alertas') }}"><span><small><i class="fa fa-fw fa-bell"></i></small> Alertas</span></a></li>
                                <li><a href="{{ url('/megafamilia/tareas') }}"><span><small><i class="fa fa-fw fa-tasks"></i></small> Tareas</span></a></li>
                                <li><a href="{{ url('/megafamilia/ubicaciones') }}"><span><small><i class="fa fa-fw fa-map-marker-alt"></i></small> Ubicaciones</span></a></li>
                                <li><a href="{{ url('/megafamilia/geofences') }}"><span><small><i class="fa fa-fw fa-draw-polygon"></i></small> Geofences</span></a></li>
                                <li><a href="{{ url('/megafamilia/reportes') }}"><span><small><i class="fa fa-fw fa-chart-bar"></i></small> Reportes</span></a></li>
                                @can('megafamilia_admin')
                                    <li><a href="{{ url('/megafamilia/configuracion') }}"><span><small><i class="fa fa-fw fa-cog"></i></small> Configuración</span></a></li>
                                @endcan
                            </ul>
                        </li>
                    @endcanany
                @endhasanyrole

                {{-- 12. Embajadores --}}
                @canany(['embajadores.view', 'embajadores.configure'])
                    <li>
                        <a href="javascript: void(0);" class="has-arrow">
                            <i data-feather="award"></i>
                            <span data-key="t-embajadores">Embajadores</span>
                        </a>
                        <ul class="sub-menu" aria-expanded="false">
                            @can('embajadores.view')
                                <li><a href="{{ url('/embajadores') }}"><span><small><i class="fa fa-fw fa-tachometer-alt"></i></small> Dashboard</span></a></li>
                                <li><a href="{{ url('/embajadores/clientes') }}"><span><small><i class="fa fa-fw fa-handshake"></i></small> Embajadores</span></a></li>
                                <li><a href="{{ url('/embajadores/comisiones') }}"><span><small><i class="fa fa-fw fa-coins"></i></small> Comisiones</span></a></li>
                            @endcan
                            @can('embajadores.configure')
                                <li><a href="{{ url('/embajadores/tiers') }}"><span><small><i class="fa fa-fw fa-percentage"></i></small> Porcentajes</span></a></li>
                                <li><a href="{{ url('/embajadores/configuracion') }}"><span><small><i class="fa fa-fw fa-cog"></i></small> Configuración</span></a></li>
                            @endcan
                        </ul>
                    </li>
                @endcanany

                {{-- 13. War Room — location:primary (addon-warroom) --}}
                @can('warroom.view')
                    <li>
                        <a href="{{ url('/warroom') }}" class="{{ request()->is('warroom*') ? 'active' : '' }}">
                            <i data-feather="target"></i>
                            <span data-key="t-warroom">War Room</span>
                        </a>
                    </li>
                @endcan

                {{-- 14. Administración --}}
                @can('admin_view_module')
                    <li>
                        <a href="{{ url('/administracion') }}" class="has-arrow">
                            <i data-feather="command"></i>
                            <span data-key="t-administracion">Administración</span>
                        </a>
                    </li>
                @endcan

                {{-- 15. Configuración --}}
                @can('config_view_module')
                    <li>
                        <a href="{{ url('/configuracion') }}" class="has-arrow">
                            <i data-feather="tool"></i>
                            <span data-key="t-configuracion">Configuración</span>
                        </a>
                    </li>
                @endcan

                {{-- Desarrollador — accordion, rol DESARROLLADOR o super-administrator (addon-devtools, location:developer) --}}
                @hasanyrole('DESARROLLADOR|super-administrator')
                    <li class="menu-item-desarrollador">
                        <a href="javascript: void(0);" class="has-arrow link-desarrollador">
                            <i data-feather="code"></i>
                            <span data-key="t-desarrollador">Desarrollador</span>
                        </a>
                        <ul class="sub-menu" aria-expanded="false">
                            <li>
                                <a href="{{ url('/devtools') }}">
                                    <span data-key="t-devtools"><small><i class="fa fa-fw fa-terminal"></i></small> DevTools</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                @endhasanyrole

            </ul>
        </div>
        <!-- Sidebar -->
    </div>
</div>

<style>
    /* Separador de sección dentro de un sub-menu (ej. "Marketing" dentro de Finanzas) */
    #side-menu .sidebar-section-divider {
        padding: 10px 20px 4px;
        pointer-events: none;
        list-style: none;
    }
    #side-menu .sidebar-section-label {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: var(--bs-secondary-color, #6c757d);
        opacity: 0.75;
    }
    [data-layout-mode="dark"] #side-menu .sidebar-section-label,
    [data-topbar="dark"] #side-menu .sidebar-section-label {
        color: rgba(255, 255, 255, 0.4);
        opacity: 1;
    }

    /* Desarrollador — acento naranja, solo visible con role:DESARROLLADOR */
    #side-menu .menu-item-desarrollador > a.link-desarrollador {
        color: #ff8c00 !important;
        font-weight: 600;
        border-left: 3px solid #ff8c00;
        background: rgba(255, 140, 0, 0.06);
    }
    #side-menu .menu-item-desarrollador > a.link-desarrollador:hover {
        color: #ffffff !important;
        background: #ff8c00;
    }
    #side-menu .menu-item-desarrollador > a.link-desarrollador svg {
        color: #ff8c00;
    }
    #side-menu .menu-item-desarrollador > a.link-desarrollador:hover svg {
        color: #ffffff;
    }
    [data-layout-mode="dark"] #side-menu .menu-item-desarrollador > a.link-desarrollador {
        background: rgba(255, 140, 0, 0.10);
    }
</style>
