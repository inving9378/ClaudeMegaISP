<li>
    @if(auth()->user()->canAny(['client_view_dashboard', 'client_view_client', 'client_add_client']))
        <a href="javascript: void(0);" class="has-arrow">
            <i data-feather="user-check"></i>
            <span data-key="t-cliente">{{ $item->sidebar_label ?? 'Clientes' }}</span>
        </a>
    @endcanany
    <ul class="sub-menu" aria-expanded="false">
        @if(auth()->user()->can('client_view_dashboard'))
            <li>
                <a href="{{ url('/cliente/') }}">
                    <span data-key="t-cliente-dashboard"><small><i class="fas fa-table"></i></small> Dashboard</span>
                </a>
            </li>
        @endif
        @if(auth()->user()->can('client_add_client'))
            <li>
                <a href="{{ url('/cliente/crear') }}">
                    <span data-key="t-cliente-crear"><small><i class="fa fa-fw fa-user"></i></small> Crear</span>
                </a>
            </li>
        @endif
        @if(auth()->user()->can('client_view_client'))
            <li>
                <a href="{{ url('/cliente/listar') }}">
                    <span data-key="t-cliente-listar"><small><i class="fa fa-fw fa-list"></i></small> Listar</span>
                </a>
            </li>
        @endif

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
