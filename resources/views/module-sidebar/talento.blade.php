@if(auth()->user()->canAny(['talento.view','talento.work_orders.view','talento.compensation.view','talento.liquidation.view','talento.attendance.view','talento.location.view','talento.work_sites.view','talento.custody.view','talento.devices.view','talento.roadmap.view','talento.caja.view','talento.routes.view','talento.projects.view','talento.quality.view','talento.penalties.view','talento.credentials.view','talento.loans.view','talento.academy.view','talento.levels.view','talento.dashboard.view','talento.escalafon.view','talento.embajadores.view']))
<li>
    <a href="javascript: void(0);" class="has-arrow">
        <i data-feather="users"></i>
        <span data-key="t-talento">{{ $item->sidebar_label ?? 'Talento' }}</span>
    </a>
    {{--
        David, 30-sep-2026: el recorte de links sueltos (28-sep) valía solo
        para quien NO tiene talento.employees.view — a quien SÍ lo tiene
        (roster completo, hoy admin/DESARROLLADOR/Mostrador) se le seguía
        mostrando el listado suelto de cada pantalla de abajo, a propósito,
        porque en ese momento se consideró que para ellos no era redundante
        (podían ver TODOS los colaboradores de un jalón en una tabla plana,
        cosa que la ficha —siempre de UN colaborador a la vez— no ofrece).
        David pidió explícitamente recortarlo IGUAL para roster completo:
        ahora /talento ya trae el dashboard + la tabla completa arriba, y
        cada fila abre la ficha con las mismas pestañas — así que el link
        suelto es duplicado también aquí. Las rutas de abajo SIGUEN
        funcionando por URL directa (nada se borró del backend); solo se
        quitó el atajo del menú. Si algún día hace falta un vistazo
        TRANSVERSAL (todas las órdenes de todos los colaboradores en una
        sola tabla, no una ficha a la vez), es una pantalla nueva a
        construir aparte — no motivo para revivir este bloque.
    --}}
    @php($rosterCompleto = auth()->user()->can('talento.employees.view'))
    <ul class="sub-menu" aria-expanded="false">
        @if(auth()->user()->can('talento.view'))
            <li><a href="{{ url('/talento') }}"><span><small><i class="fa fa-fw fa-id-badge"></i></small> {{ $rosterCompleto ? 'Colaboradores' : 'Dashboard' }}</span></a></li>
        @endif
        {{-- Catálogos/vistas globales — NUNCA son "de una persona", no tienen
             equivalente en ninguna pestaña, se quedan para quien tenga el
             permiso puntual de cada una sin importar si tiene roster completo. --}}
        @if(auth()->user()->can('talento.puestos.manage'))
            <li><a href="{{ url('/talento/puestos') }}"><span><small><i class="fa fa-fw fa-briefcase"></i></small> Catálogo de puestos</span></a></li>
        @endif
        @if(auth()->user()->can('talento.levels.view'))
            <li><a href="{{ url('/talento/niveles') }}"><span><small><i class="fa fa-fw fa-layer-group"></i></small> Niveles</span></a></li>
        @endif
        @if(auth()->user()->can('talento.escalafon.view'))
            <li><a href="{{ url('/talento/escalafon') }}"><span><small><i class="fa fa-fw fa-trophy"></i></small> Escalafón</span></a></li>
        @endif
        @if(auth()->user()->can('talento.location.view'))
            <li><a href="{{ url('/talento/mapa-en-vivo') }}"><span><small><i class="fa fa-fw fa-map-marked-alt"></i></small> Mapa en vivo</span></a></li>
        @endif
        @if(auth()->user()->can('talento.work_sites.view'))
            <li><a href="{{ url('/talento/sitios') }}"><span><small><i class="fa fa-fw fa-map-pin"></i></small> Sitios de checada</span></a></li>
        @endif
        @if(auth()->user()->can('talento.roadmap.view'))
            <li><a href="{{ url('/talento/roadmap') }}"><span><small><i class="fa fa-fw fa-road"></i></small> Roadmap</span></a></li>
        @endif

        {{-- Hijos dinámicos desde module_sidebar_config (Fase 2.3/3.5) --}}
        @foreach($item->dynamic_children ?? collect() as $child)
            @php($childPermission = $child->permission ?? $item->permission ?? null)
            @if(!$childPermission || auth()->user()->can($childPermission))
            <li><a href="{{ $child->sidebar_url ? url($child->sidebar_url) : url('/' . $child->module_key) }}"><span>@if($child->sidebar_icon)<small><i class="{{ $child->sidebar_icon }}"></i></small> @endif{{ $child->sidebar_label ?? $child->module_key }}</span></a></li>
            @endif
        @endforeach
    </ul>
</li>
@endcanany
