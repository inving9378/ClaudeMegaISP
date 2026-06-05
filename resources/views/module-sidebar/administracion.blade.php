@can('admin_view_module')
    <li>
        <a href="{{ url('/administracion') }}" class="has-arrow">
            <i data-feather="command"></i>
            <span data-key="t-administracion">{{ $item->sidebar_label ?? 'Administración' }}</span>
        </a>
    </li>
@endcan
