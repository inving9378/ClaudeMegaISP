@can('dashboard_view_dashboard')
    <li>
        <a href="{{ url('/') }}">
            <i data-feather="home"></i>
            <span data-key="t-dashboard">{{ $item->sidebar_label ?? 'Dashboard' }}</span>
        </a>
    </li>
@endcan
