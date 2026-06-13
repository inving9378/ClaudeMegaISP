@hasanyrole('DESARROLLADOR|Administrador|Super Administrador|super-administrator|TECNICO')
    @if(auth()->user()->canAny(['megafamilia_admin', 'megafamilia_support']))
        <li>
            <a href="javascript: void(0);" class="has-arrow">
                <i data-feather="shield"></i>
                <span data-key="t-megafamilia">{{ $item->sidebar_label ?? 'MegaFamilia' }}</span>
            </a>
            <ul class="sub-menu" aria-expanded="false">
                @if(auth()->user()->can('megafamilia_admin'))
                    <li><a href="{{ url('/megafamilia') }}"><span><small><i class="fa fa-fw fa-tachometer-alt"></i></small> Dashboard</span></a></li>
                    <li><a href="{{ url('/megafamilia/clientes') }}"><span><small><i class="fa fa-fw fa-users"></i></small> Clientes</span></a></li>
                    <li><a href="{{ url('/megafamilia/licencias') }}"><span><small><i class="fa fa-fw fa-key"></i></small> Licencias</span></a></li>
                    <li><a href="{{ url('/megafamilia/planes') }}"><span><small><i class="fa fa-fw fa-layer-group"></i></small> Planes</span></a></li>
                    <li><a href="{{ url('/megafamilia/perfiles') }}"><span><small><i class="fa fa-fw fa-child"></i></small> Perfiles</span></a></li>
                @endif
                <li><a href="{{ url('/megafamilia/dispositivos') }}"><span><small><i class="fa fa-fw fa-mobile-screen"></i></small> Dispositivos</span></a></li>
                <li><a href="{{ url('/megafamilia/solicitudes') }}"><span><small><i class="fa fa-fw fa-inbox"></i></small> Solicitudes</span></a></li>
                <li><a href="{{ url('/megafamilia/alertas') }}"><span><small><i class="fa fa-fw fa-bell"></i></small> Alertas</span></a></li>
                <li><a href="{{ url('/megafamilia/tareas') }}"><span><small><i class="fa fa-fw fa-tasks"></i></small> Tareas</span></a></li>
                <li><a href="{{ url('/megafamilia/ubicaciones') }}"><span><small><i class="fa fa-fw fa-map-marker-alt"></i></small> Ubicaciones</span></a></li>
                <li><a href="{{ url('/megafamilia/geofences') }}"><span><small><i class="fa fa-fw fa-draw-polygon"></i></small> Geofences</span></a></li>
                <li><a href="{{ url('/megafamilia/reportes') }}"><span><small><i class="fa fa-fw fa-chart-bar"></i></small> Reportes</span></a></li>
                @if(auth()->user()->can('megafamilia_admin'))
                    <li><a href="{{ url('/megafamilia/configuracion') }}"><span><small><i class="fa fa-fw fa-cog"></i></small> Configuración</span></a></li>
                @endif

                {{-- Hijos dinámicos desde module_sidebar_config (Fase 2.3/3.5) --}}
                @foreach($item->dynamic_children ?? collect() as $child)
                    <li><a href="{{ $child->sidebar_url ? url($child->sidebar_url) : url('/' . $child->module_key) }}"><span>@if($child->sidebar_icon)<small><i class="{{ $child->sidebar_icon }}"></i></small> @endif{{ $child->sidebar_label ?? $child->module_key }}</span></a></li>
                @endforeach
            </ul>
        </li>
    @endcanany
@endhasanyrole
