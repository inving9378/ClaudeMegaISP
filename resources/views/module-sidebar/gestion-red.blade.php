<li>
    @if(auth()->user()->canAny(['router_view_router', 'ipv4_view_ipv4', 'router_add_router', 'ipv4_add_ipv4']))
        <a href="javascript: void(0);" class="has-arrow">
            <i data-feather="box"></i>
            <span data-key="t-gestion-red">{{ $item->sidebar_label ?? 'Gestión de red' }}</span>
        </a>
    @endcanany
    <ul class="sub-menu" aria-expanded="false">
        <li>
            @if(auth()->user()->canAny(['router_add_router', 'router_view_router']))
                <a href="javascript: void(0);" class="has-arrow">
                    <i data-feather="box"></i>
                    <span data-key="t-router">Enrutadores</span>
                </a>
            @endcanany
            <ul class="sub-menu" aria-expanded="false">
                @if(auth()->user()->can('router_add_router'))
                    <li>
                        <a href="{{ url('/red/router/crear') }}">
                            <span data-key="t-router-crear"><small><i class="fa fa-fw fa-puzzle-piece"></i></small> Add</span>
                        </a>
                    </li>
                @endif
                @if(auth()->user()->can('router_view_router'))
                    <li>
                        <a href="{{ url('/red/router/listar') }}">
                            <span data-key="t-router-listar"><small><i class="fa fa-fw fa-list"></i></small> Listar</span>
                        </a>
                    </li>
                @endif
            </ul>
        </li>
        <li>
            @if(auth()->user()->canAny(['ipv4_add_ipv4', 'ipv4_view_ipv4']))
                <a href="javascript: void(0);" class="has-arrow">
                    <i data-feather="box"></i>
                    <span data-key="t-ipv4">Redes IPv4</span>
                </a>
            @endcanany
            <ul class="sub-menu" aria-expanded="false">
                @if(auth()->user()->can('ipv4_add_ipv4'))
                    <li>
                        <a href="{{ url('/red/ipv4/crear') }}">
                            <span data-key="t-ipv4-crear"><small><i class="fa fa-fw fa-puzzle-piece"></i></small> Add</span>
                        </a>
                    </li>
                @endif
                @if(auth()->user()->can('ipv4_view_ipv4'))
                    <li>
                        <a href="{{ url('/red/ipv4/listar') }}">
                            <span data-key="t-ipv4-listar"><small><i class="fa fa-fw fa-list"></i></small> Listar</span>
                        </a>
                    </li>
                @endif
            </ul>
        </li>

        {{-- Hijos dinámicos desde module_sidebar_config (Fase 2.3/3.5) --}}
        @foreach($item->dynamic_children ?? collect() as $child)
            <li>
                <a href="{{ $child->sidebar_url ? url($child->sidebar_url) : url('/' . $child->module_key) }}">
                    <span>@if($child->sidebar_icon)<small><i class="{{ $child->sidebar_icon }}"></i></small> @endif{{ $child->sidebar_label ?? $child->module_key }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</li>
