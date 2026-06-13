@if(auth()->user()->can('maps_view_maps'))
    <li>
        <a href="{{ url('/mapas/') }}">
            <i data-feather="map"></i>
            <span data-key="t-mapas">{{ $item->sidebar_label ?? 'Mapas' }}</span>
        </a>
    </li>
@endif
