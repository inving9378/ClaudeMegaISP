@if(auth()->user()->can('centro-proyecto.view'))
    <li>
        <a href="{{ url('/centro-proyecto') }}">
            <i data-feather="activity"></i>
            <span data-key="t-centro-proyecto">{{ $item->sidebar_label ?? 'Centro de Proyecto' }}</span>
        </a>
    </li>
@endif
