@can('crm_view_crm')
    <li>
        <a href="javascript: void(0);" class="has-arrow">
            <i data-feather="user-x"></i>
            <span data-key="t-crm">{{ $item->sidebar_label ?? 'Clientes potenciales' }}</span>
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

            {{-- Hijos dinámicos desde module_sidebar_config (Fase 2.3/3.5) --}}
            @foreach($item->dynamic_children ?? collect() as $child)
                <li>
                    <a href="{{ url('/' . $child->module_key) }}">
                        <span>@if($child->sidebar_icon)<small><i class="{{ $child->sidebar_icon }}"></i></small> @endif{{ $child->sidebar_label ?? $child->module_key }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </li>
@endcan
