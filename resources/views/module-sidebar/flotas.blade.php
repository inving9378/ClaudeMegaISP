@canany(['fleet.view', 'fleet.gps.view'])
    <li>
        <a href="javascript: void(0);" class="has-arrow">
            <i data-feather="truck"></i>
            <span data-key="t-flotas">{{ $item->sidebar_label ?? 'Flotas' }}</span>
        </a>
        <ul class="sub-menu" aria-expanded="false">
            @can('fleet.view')
                <li><a href="{{ url('/flotas') }}"><span><small><i class="fa fa-fw fa-tachometer-alt"></i></small> Dashboard</span></a></li>
                <li><a href="{{ url('/flotas/vehiculos') }}"><span><small><i class="fa fa-fw fa-car"></i></small> Vehículos</span></a></li>
            @endcan
            @can('fleet.gps.view')
                <li><a href="{{ url('/flotas/mapa') }}"><span><small><i class="fa fa-fw fa-map-marked-alt"></i></small> Mapa</span></a></li>
            @endcan
            @can('fleet.geofences.view')
                <li><a href="{{ url('/flotas/geocercas') }}"><span><small><i class="fa fa-fw fa-draw-polygon"></i></small> Geocercas</span></a></li>
            @endcan
            @can('fleet.notifications.view')
                <li><a href="{{ url('/flotas/notificaciones-log') }}"><span><small><i class="fa fa-fw fa-bell"></i></small> Notificaciones</span></a></li>
            @endcan
            @can('fleet.rules.view')
                <li><a href="{{ url('/flotas/reglas') }}"><span><small><i class="fa fa-fw fa-filter"></i></small> Reglas de alertas</span></a></li>
            @endcan
            @can('fleet.documents.view')
                <li><a href="{{ url('/flotas/documentos') }}"><span><small><i class="fa fa-fw fa-folder-open"></i></small> Documentos</span></a></li>
            @endcan
            @can('fleet.subscriptions.manage')
                <li><a href="{{ url('/flotas/suscripciones') }}"><span><small><i class="fa fa-fw fa-credit-card"></i></small> Suscripciones</span></a></li>
            @endcan
        </ul>
    </li>
@endcanany
