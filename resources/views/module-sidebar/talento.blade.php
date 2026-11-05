@if(auth()->user()->canAny(['talento.view','talento.work_orders.view','talento.compensation.view','talento.liquidation.view','talento.attendance.view','talento.location.view','talento.work_sites.view','talento.custody.view','talento.devices.view','talento.caja.view','talento.routes.view','talento.projects.view','talento.quality.view','talento.penalties.view','talento.credentials.view','talento.loans.view','talento.academy.view','talento.dashboard.view','talento.escalafon.view','talento.embajadores.view']))
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
        {{-- 2026-10-22: el Mostrador ya no ve la lista de colaboradores (entra a su dashboard, como un
             técnico); desde aquí sigue creando órdenes y ve cuánto cobra cada quien. --}}
        @if(auth()->user()->can('talento.work_orders.manage'))
            <li><a href="{{ url('/talento/ordenes') }}"><span><small><i class="fa fa-fw fa-clipboard-list"></i></small> Órdenes de trabajo</span></a></li>
        @endif
        @if(auth()->user()->can('talento.liquidation.totales'))
            <li><a href="{{ url('/talento/liquidaciones') }}"><span><small><i class="fa fa-fw fa-file-invoice-dollar"></i></small> Liquidaciones</span></a></li>
        @endif
        {{-- Catálogos/vistas globales — NUNCA son "de una persona", no tienen
             equivalente en ninguna pestaña, se quedan para quien tenga el
             permiso puntual de cada una sin importar si tiene roster completo. --}}
        {{-- 2026-10-27: las pantallas de gestión general que el recorte del 30-sep dejó solo por
             dirección. Academia va con .manage: el .view lo tienen técnicos y Mostrador, que ya
             tienen su Academia en la ficha. --}}
        @if(auth()->user()->can('talento.academy.manage'))
            <li><a href="{{ url('/talento/academia') }}"><span><small><i class="fa fa-fw fa-graduation-cap"></i></small> Academia</span></a></li>
        @endif
        @if(auth()->user()->can('talento.credentials.view'))
            <li><a href="{{ url('/talento/credenciales') }}"><span><small><i class="fa fa-fw fa-id-card"></i></small> Credenciales</span></a></li>
        @endif
        @if(auth()->user()->can('talento.penalties.view'))
            <li><a href="{{ url('/talento/penalizaciones') }}"><span><small><i class="fa fa-fw fa-gavel"></i></small> Penalizaciones</span></a></li>
        @endif
        @if(auth()->user()->can('talento.reglas_desempeno.view'))
            <li><a href="{{ url('/talento/reglas-desempeno') }}"><span><small><i class="fa fa-fw fa-balance-scale"></i></small> Reglas de desempeño</span></a></li>
        @endif
        @if(auth()->user()->can('talento.documentos.ver-todos'))
            <li><a href="{{ url('/talento/documentos-pendientes') }}"><span><small><i class="fa fa-fw fa-file-signature"></i></small> Documentos pendientes</span></a></li>
        @endif
        @if(auth()->user()->can('talento.expediente.paquetes.manage'))
            <li><a href="{{ url('/talento/expediente/paquetes') }}"><span><small><i class="fa fa-fw fa-folder-open"></i></small> Paquetes de documentos</span></a></li>
        @endif
        @if(auth()->user()->can('talento.config.evidencias'))
            <li><a href="{{ url('/talento/config/evidencias') }}"><span><small><i class="fa fa-fw fa-camera"></i></small> Configuración de evidencias</span></a></li>
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
