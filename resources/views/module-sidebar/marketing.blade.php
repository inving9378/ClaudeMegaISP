{{--
    Submenú lateral de Marketing (Fase 2, §2.11 de docs/marketing/fase-2-paquetes.md, aprobado por Henry).
    - Solo lo ve quien pasa la regla de acceso de la Fase 1 (usuario activo + equipo fijado + fila «admin» activa en
      Meganet): EquipoMarketing::puedeEntrar. Nadie más ve «Marketing».
    - Sale del mismo `module.json → menu[0].children` que la barra interna del módulo (MktNavegacionModulo), en su
      orden: reordenar = editar module.json. Las pestañas de cada sección (`sub`) viven en la barra interna, no aquí.
    - Contenido (Fase 3) y Analítica (Fase 5) aún no se muestran: no están en module.json.
    - Diseño unificado (§2.13, precisión de Irving): clic en «Marketing» entra DIRECTO al Panel (`href` real, que
      metisMenu no bloquea porque no es «#») y despliega el submenú. Si el grupo ya estaba abierto, el `onclick`
      marca el enlace como `aria-disabled` durante ese clic para que metisMenu no lo cierre (metisMenu ignora los
      disparadores con aria-disabled="true"); la navegación sigue igual (SPA). Cada hijo lleva `data-key` como Clientes.
--}}
@php
    $mktUsuario = auth()->user();
    $mktItems = [];
    if (\App\Modules\Addons\Marketing\Support\EquipoMarketing::puedeEntrar($mktUsuario)) {
        foreach (\App\Modules\Addons\Marketing\Controllers\NavegacionController::menu() as $mktItem) {
            $mktPermiso = $mktItem['permission'] ?? null;
            if (! in_array('admin', (array) ($mktItem['roles_empresa'] ?? []), true)) {
                continue;
            }
            if ($mktPermiso && ! $mktUsuario->can($mktPermiso)) {
                continue;
            }
            $mktItems[] = $mktItem;
        }
    }
@endphp
@if(count($mktItems))
    <li>
        <a href="{{ url('/marketing') }}" class="has-arrow" data-mkt-entrada
           onclick="if (this.parentNode.classList.contains('mm-active')) { var a = this; a.setAttribute('aria-disabled', 'true'); setTimeout(function () { a.removeAttribute('aria-disabled'); }, 0); }">
            <i data-feather="target"></i>
            <span data-key="t-marketing">{{ $item->sidebar_label ?? 'Marketing' }}</span>
        </a>
        <ul class="sub-menu" aria-expanded="false" data-mkt-submenu>
            @foreach($mktItems as $mktItem)
                <li>
                    <a href="{{ url($mktItem['url']) }}" data-mkt-item="{{ \Illuminate\Support\Str::slug($mktItem['label']) }}">
                        <span data-key="t-marketing-{{ \Illuminate\Support\Str::slug($mktItem['label']) }}"><small><i class="fa fa-fw {{ $mktItem['icono_fa'] ?? 'fa-circle' }}"></i></small> {{ $mktItem['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </li>
@endif
