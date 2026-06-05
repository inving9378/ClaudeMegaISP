<li>
    @canany(['router_view_router', 'ipv4_view_ipv4', 'router_add_router', 'ipv4_add_ipv4'])
        <a href="javascript: void(0);" class="has-arrow">
            <i data-feather="box"></i>
            <span data-key="t-gestion-red">{{ $item->sidebar_label ?? 'Gestión de red' }}</span>
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
