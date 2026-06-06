<li>
    @canany(['finance_view_transactions', 'finance_view_billing', 'finance_view_payments',
             'finance_view_invoices', 'finance_view_general_accounting', 'payments_manage_providers'])
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

        {{-- Hijos dinámicos desde module_sidebar_config (Fase 2.3/3.5).
             Marketing dejó de ser caso especial: ahora es módulo top-level
             con su propio partial (module-sidebar/marketing.blade.php). --}}
        @foreach($item->dynamic_children ?? collect() as $child)
            <li>
                <a href="{{ $child->sidebar_url ? url($child->sidebar_url) : url('/' . $child->module_key) }}">
                    <span>@if($child->sidebar_icon)<small><i class="{{ $child->sidebar_icon }}"></i></small> @endif{{ $child->sidebar_label ?? $child->module_key }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</li>
