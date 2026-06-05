@can('config_view_module')
    <li>
        <a href="{{ url('/configuracion') }}" class="has-arrow">
            <i data-feather="tool"></i>
            <span data-key="t-configuracion">{{ $item->sidebar_label ?? 'Configuración' }}</span>
        </a>
    </li>
@endcan
