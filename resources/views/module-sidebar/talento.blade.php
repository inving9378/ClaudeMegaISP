@canany(['talento.view','talento.work_orders.view','talento.compensation.view','talento.liquidation.view','talento.attendance.view','talento.location.view','talento.work_sites.view','talento.custody.view','talento.devices.view','talento.roadmap.view'])
<li>
    <a href="javascript: void(0);" class="has-arrow">
        <i data-feather="users"></i>
        <span data-key="t-talento">{{ $item->sidebar_label ?? 'Talento' }}</span>
    </a>
    <ul class="sub-menu" aria-expanded="false">
        @can('talento.view')
            <li><a href="{{ url('/talento') }}"><span><small><i class="fa fa-fw fa-id-badge"></i></small> Colaboradores</span></a></li>
        @endcan
        @can('talento.work_orders.view')
            <li><a href="{{ url('/talento/ordenes') }}"><span><small><i class="fa fa-fw fa-clipboard-list"></i></small> Órdenes de trabajo</span></a></li>
        @endcan
        @can('talento.compensation.view')
            <li><a href="{{ url('/talento/compensacion') }}"><span><small><i class="fa fa-fw fa-coins"></i></small> Compensación</span></a></li>
        @endcan
        @can('talento.liquidation.view')
            <li><a href="{{ url('/talento/liquidaciones') }}"><span><small><i class="fa fa-fw fa-file-invoice-dollar"></i></small> Liquidaciones</span></a></li>
        @endcan
        @can('talento.attendance.view')
            <li><a href="{{ url('/talento/asistencia') }}"><span><small><i class="fa fa-fw fa-calendar-check"></i></small> Asistencia</span></a></li>
        @endcan
        @can('talento.work_orders.view')
            <li><a href="{{ url('/talento/campo') }}"><span><small><i class="fa fa-fw fa-hard-hat"></i></small> Flujo de campo</span></a></li>
        @endcan
        @can('talento.caja.view')
            <li><a href="{{ url('/talento/cajas') }}"><span><small><i class="fa fa-fw fa-signal"></i></small> Cajas ODB</span></a></li>
        @endcan
        @can('talento.routes.view')
            <li><a href="{{ url('/talento/rutas') }}"><span><small><i class="fa fa-fw fa-route"></i></small> Rutas planta</span></a></li>
        @endcan
        @can('talento.projects.view')
            <li><a href="{{ url('/talento/proyectos') }}"><span><small><i class="fa fa-fw fa-project-diagram"></i></small> Proyectos</span></a></li>
        @endcan
        @can('talento.quality.view')
            <li><a href="{{ url('/talento/calidad') }}"><span><small><i class="fa fa-fw fa-clipboard-check"></i></small> Calidad de caja</span></a></li>
        @endcan
        @can('talento.penalties.view')
            <li><a href="{{ url('/talento/penalizaciones') }}"><span><small><i class="fa fa-fw fa-gavel"></i></small> Penalizaciones</span></a></li>
        @endcan
        @can('talento.credentials.view')
            <li><a href="{{ url('/talento/credenciales') }}"><span><small><i class="fa fa-fw fa-id-card"></i></small> Credenciales</span></a></li>
        @endcan
        @can('talento.loans.view')
            <li><a href="{{ url('/talento/finiquito') }}"><span><small><i class="fa fa-fw fa-hand-holding-usd"></i></small> Préstamos y finiquito</span></a></li>
        @endcan
        @can('talento.academy.view')
            <li><a href="{{ url('/talento/academia') }}"><span><small><i class="fa fa-fw fa-graduation-cap"></i></small> Academia</span></a></li>
        @endcan
        @can('talento.levels.view')
            <li><a href="{{ url('/talento/niveles') }}"><span><small><i class="fa fa-fw fa-layer-group"></i></small> Niveles</span></a></li>
        @endcan
        @can('talento.dashboard.view')
            <li><a href="{{ url('/talento/dashboard') }}"><span><small><i class="fa fa-fw fa-tachometer-alt"></i></small> Dashboard</span></a></li>
        @endcan
        @can('talento.escalafon.view')
            <li><a href="{{ url('/talento/escalafon') }}"><span><small><i class="fa fa-fw fa-trophy"></i></small> Escalafón</span></a></li>
        @endcan
        @can('talento.embajadores.view')
            <li><a href="{{ url('/talento/embajadores-colabs') }}"><span><small><i class="fa fa-fw fa-link"></i></small> Roles múltiples</span></a></li>
        @endcan
        @can('talento.location.view')
            <li><a href="{{ url('/talento/mapa-en-vivo') }}"><span><small><i class="fa fa-fw fa-map-marked-alt"></i></small> Mapa en vivo</span></a></li>
        @endcan
        @can('talento.work_sites.view')
            <li><a href="{{ url('/talento/sitios') }}"><span><small><i class="fa fa-fw fa-map-pin"></i></small> Sitios de checada</span></a></li>
        @endcan
        @can('talento.custody.view')
            <li><a href="{{ url('/talento/custodia') }}"><span><small><i class="fa fa-fw fa-boxes"></i></small> Custodia</span></a></li>
        @endcan
        @can('talento.devices.view')
            <li><a href="{{ url('/talento/dispositivos') }}"><span><small><i class="fa fa-fw fa-mobile-alt"></i></small> Dispositivos</span></a></li>
        @endcan
        @can('talento.roadmap.view')
            <li><a href="{{ url('/talento/roadmap') }}"><span><small><i class="fa fa-fw fa-road"></i></small> Roadmap</span></a></li>
        @endcan
    </ul>
</li>
@endcanany
