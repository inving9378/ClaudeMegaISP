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
            <span data-key="t-finanzas">{{ $item->sidebar_label ?? 'Finanzas' }}</span>
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

        {{-- Facturación — Notificaciones pendientes --}}
        @can('facturacion.notif.gestionar')
            <li>
                <a href="{{ url('/finanzas/notificaciones-pendientes') }}">
                    <span data-key="t-finanzas-notif"><small><i class="fas fa-paper-plane"></i></small> Notificaciones pendientes</span>
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

        {{-- Hijos dinámicos desde module_sidebar_config (Fase 2.3/3.5).
             Se EXCLUYE 'marketing': ya está renderizado arriba con su submenú
             rico hardcodeado (fuente de verdad de permisos). El resto de hijos
             sub_item de finanzas (ej. cobranza-blaster) sí salen aquí. --}}
        @foreach(($item->dynamic_children ?? collect())->where('module_key', '!=', 'marketing') as $child)
            <li>
                <a href="{{ url('/' . $child->module_key) }}">
                    <span>@if($child->sidebar_icon)<small><i class="{{ $child->sidebar_icon }}"></i></small> @endif{{ $child->sidebar_label ?? $child->module_key }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</li>
