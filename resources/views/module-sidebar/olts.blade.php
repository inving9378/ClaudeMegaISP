@if(auth()->user()->can('olt_view'))
    <li>
        <a href="{{ url('/olts') }}">
            <i data-feather="server"></i>
            <span data-key="t-olts">{{ $item->sidebar_label ?? 'OLTs' }}</span>
        </a>
    </li>
@endif
