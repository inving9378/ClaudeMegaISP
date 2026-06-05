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
        </ul>
    </li>
@endcan
