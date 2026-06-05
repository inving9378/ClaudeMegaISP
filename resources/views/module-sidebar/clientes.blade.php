<li>
    @canany(['client_view_dashboard', 'client_view_client', 'client_add_client'])
        <a href="javascript: void(0);" class="has-arrow">
            <i data-feather="user-check"></i>
            <span data-key="t-cliente">{{ $item->sidebar_label ?? 'Clientes' }}</span>
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
                <a href="{{ url('/cliente/listar') }}"
                   hx-get="{{ url('/cliente/listar') }}"
                   hx-target="#htmx-main"
                   hx-swap="outerHTML"
                   hx-select="#htmx-main"
                   hx-push-url="true">
                    <span data-key="t-cliente-listar"><small><i class="fa fa-fw fa-list"></i></small> Listar</span>
                </a>
            </li>
        @endcan
    </ul>
</li>
