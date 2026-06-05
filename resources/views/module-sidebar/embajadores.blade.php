@canany(['embajadores.view', 'embajadores.configure'])
    <li>
        <a href="javascript: void(0);" class="has-arrow">
            <i data-feather="award"></i>
            <span data-key="t-embajadores">{{ $item->sidebar_label ?? 'Embajadores' }}</span>
        </a>
        <ul class="sub-menu" aria-expanded="false">
            @can('embajadores.view')
                <li><a href="{{ url('/embajadores') }}"><span><small><i class="fa fa-fw fa-tachometer-alt"></i></small> Dashboard</span></a></li>
                <li><a href="{{ url('/embajadores/clientes') }}"><span><small><i class="fa fa-fw fa-handshake"></i></small> Embajadores</span></a></li>
                <li><a href="{{ url('/embajadores/comisiones') }}"><span><small><i class="fa fa-fw fa-coins"></i></small> Comisiones</span></a></li>
            @endcan
            @can('embajadores.configure')
                <li><a href="{{ url('/embajadores/tiers') }}"><span><small><i class="fa fa-fw fa-percentage"></i></small> Porcentajes</span></a></li>
                <li><a href="{{ url('/embajadores/configuracion') }}"><span><small><i class="fa fa-fw fa-cog"></i></small> Configuración</span></a></li>
            @endcan

            {{-- Hijos dinámicos desde module_sidebar_config (Fase 2.3/3.5) --}}
            @foreach($item->dynamic_children ?? collect() as $child)
                <li><a href="{{ url('/' . $child->module_key) }}"><span>@if($child->sidebar_icon)<small><i class="{{ $child->sidebar_icon }}"></i></small> @endif{{ $child->sidebar_label ?? $child->module_key }}</span></a></li>
            @endforeach
        </ul>
    </li>
@endcanany
