@php
    $config = $configLayout(auth()->user()->id);
    $logo = $logoMeganet();
    $logoName = $logo['name'];
    if ($logoName) {
        $logoPath = $logo['url_logo'];
        // $logoPath = str_replace('public', '/storage', $logoPath);
    }

@endphp

<header id="page-topbar">
    <div class="navbar-header">
        <div class="d-flex">
            <!-- LOGO -->
            <div class="navbar-brand-box">
                <a href="{{ url('/') }}" class="logo logo-dark">
                    <span class="logo-sm">
                        <img src="{{ $logoName ? asset($logoPath) : asset('/images/logo_meganet_oficial.png') }}"
                            alt="Logo" height="24">
                    </span>
                    <span class="logo-lg">
                        <img src="{{ $logoName ? asset($logoPath) : asset('/images/logo_meganet_oficial.png') }}"
                            alt="Logo" height="40">
                    </span>
                </a>

                <a href="{{ url('/') }}" class="logo logo-light">
                    <span class="logo-sm">
                        <img src="{{ $logoName ? asset($logoPath) : asset('/images/logo_meganet_oficial.png') }}"
                            alt="Logo" height="24">
                    </span>
                    <span class="logo-lg">
                        <img src="{{ $logoName ? asset($logoPath) : asset('/images/logo_meganet_oficial.png') }}"
                            alt="Logo" height="40">
                    </span>
                </a>
            </div>

            <button type="button" class="btn btn-sm px-3 font-size-16 header-item" id="vertical-menu-btn">
                <i class="fa fa-fw fa-bars"></i>
            </button>

            {{-- Badge de instancia de producción (solo en consumidoras: ver TopbarComposer).
                 Estilo 100% con tokens de dark-light-tokens.css → claro + oscuro, nada hardcoded. --}}
            @if ($showProductionBadge ?? false)
                <style>
                    .megaisp-prod-pill {
                        display: inline-flex;
                        align-items: center;
                        gap: .4rem;
                        padding: .22rem .7rem;
                        border-radius: 999px;
                        background: var(--bg-surface);
                        border: 1px solid var(--border-default);
                        font-size: .72rem;
                        font-weight: 600;
                        letter-spacing: .02em;
                        line-height: 1.4;
                        white-space: nowrap;
                        color: var(--success);
                    }
                    .megaisp-prod-pill__ver {
                        color: var(--text-secondary);
                        font-weight: 500;
                    }
                </style>
                <span class="header-item d-flex align-items-center" style="cursor: default;">
                    <span class="megaisp-prod-pill"
                          title="Instancia de producción · versión instalada {{ $installedVersion }}">
                        PRODUCCIÓN
                        <span class="megaisp-prod-pill__ver">· {{ $installedVersion }}</span>
                    </span>
                </span>
            @endif

            {{-- Badge CLAUDE TEST (#53): solo en instancias NO productivas (dev/test).
                 Se oculta automáticamente donde se muestra el badge de PRODUCCIÓN. --}}
            @unless ($showProductionBadge ?? false)
                <span class="header-item d-flex align-items-center" style="cursor: default;">
                    <span class="megaisp-claude-test-pill" title="Instancia de pruebas · Claude">
                        CLAUDE TEST
                    </span>
                </span>
                <style>
                    .megaisp-claude-test-pill {
                        display: inline-flex;
                        align-items: center;
                        padding: .22rem .7rem;
                        border-radius: 999px;
                        background: #FF7A00;
                        color: #fff;
                        font-size: 11.5px;
                        font-weight: 600;
                        letter-spacing: .03em;
                        line-height: 1.4;
                        white-space: nowrap;
                    }
                </style>
            @endunless
        </div>

        <div class="d-flex">
            <Mode-Visual-Body user="{{ auth()->user()->id }}" configlayout="{{ json_encode($config) }}">
            </Mode-Visual-Body>

            <!-- MANUAL_TEST_OK -->
            @if(auth()->user()->can('manual_view'))
                <button type="button"
                    class="btn header-item"
                    title="Manual de Usuario"
                    onclick="window.location.href='{{ url('/manual') }}'">
                    <i class="fas fa-book-open"></i>
                </button>
            @endif

            <button class="btn header-item" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasRight"
                aria-controls="offcanvasRight">
                <i class="far fa-folder-open"></i>
            </button>

            {{-- ENTREGABLE B — Tablero de compuertas de la Torre. Visible desde todas las
                pestañas porque la cabecera se renderiza en todas. Se controla por ROL, no por
                permiso: es una consola de estado del circuito, no un ajuste del sistema.
                Se usa auth()->user()->hasRole(...) y no @@role/@@can, que en este layout no
                evalúan (mismo motivo por el que aquí se escribe @@if(auth()->user()->can(...))). --}}
            @if(auth()->user()->hasRole('super-administrator') || auth()->user()->hasRole('DESARROLLADOR'))
                <torre-compuertas></torre-compuertas>
            @endif

            {{-- Fase 6 — Campana de conciliación de pagos pendiente (solo con permiso). --}}
            @if(auth()->user() && auth()->user()->can('conciliacion.manage'))
                <conciliacion-bell
                    endpoint="{{ route('finanzas.conciliacion-cola.pendientes') }}"
                    queue-url="{{ url('/finanzas/conciliacion-cola') }}"
                    :poll-seconds="{{ (int) config('payments.conciliacion_poll_seconds', 45) }}"
                ></conciliacion-bell>
            @endif

            @isset($notifications)
                <div class="dropdown d-inline-block">
                    <button type="button" class="btn header-item noti-icon position-relative"
                        id="page-header-notifications-dropdown" data-bs-toggle="dropdown" aria-haspopup="true"
                        aria-expanded="false">
                        <i data-feather="bell" class="icon-lg"></i>
                        <span
                            class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger hdr-badge hdr-badge-corner">{{ count($notifications) > 0 ? count($notifications) : 0 }}</span>
                    </button>
                    @if (count($notifications) > 0)
                        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0"
                            aria-labelledby="page-header-notifications-dropdown">
                            <Notification-Topbar notifications="{{ json_encode($notifications) }}"></Notification-Topbar>
                        </div>
                    @endif
                </div>
            @endisset

            <div class="dropdown d-inline-block">
                <button type="button" class="btn header-item bg-soft-light border-start border-end"
                    id="page-header-user-dropdown" data-bs-toggle="dropdown" aria-haspopup="true"
                    aria-expanded="false">
                    <img class="rounded-circle header-profile-user" src="{{ auth()->user()->url_photography }}"
                        alt="Header Avatar">
                    <span
                        class="d-none d-xl-inline-block ms-1 fw-medium">{{ \Illuminate\Support\Facades\Auth::user()->name }}</span>
                    <i class="mdi mdi-chevron-down d-none d-xl-inline-block"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    {{-- Mi portal — atajo al Portal de Colaborador. Gate: portal.colaborador
                         (@can no funciona en Blade). data-spa-skip: el portal es un SPA aparte. --}}
                    @if(auth()->user()->can('portal.colaborador'))
                        <a class="dropdown-item" href="{{ url('/talento/portal') }}" data-spa-skip>
                            <i class="mdi mdi-account-hard-hat font-size-16 align-middle me-1"></i> Mi portal
                        </a>
                        <div class="dropdown-divider"></div>
                    @endif
                    <!-- item-->
                    <a class="dropdown-item" href="{{ url('/perfil/' . auth()->user()->id) }}"><i
                            class="mdi mdi-face-profile font-size-16 align-middle me-1"></i> Perfil</a>
                    <a class="dropdown-item" href="{{ route('profile.password.show') }}"><i
                            class="mdi mdi-lock-reset font-size-16 align-middle me-1"></i> Cambiar contraseña</a>
                    <a class="dropdown-item" href="{{ route('logout') }}"
                        data-spa-skip
                        onclick="event.preventDefault(); window.__clearSidebarState && window.__clearSidebarState(); window.__logoutWithFreshCsrf ? window.__logoutWithFreshCsrf('logout-form') : document.getElementById('logout-form').submit();">
                        <i class="mdi mdi-logout font-size-16 align-middle me-1"></i> Desconectar
                    </a>

                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>

<style>
/* Centrar verticalmente los items del clúster derecho: antes solo alcanzaba a los
   .header-item hijos DIRECTOS del row, así que el engrane (torre-compuertas, hijo directo)
   quedaba centrado mientras que la campana/Jarvis/usuario (envueltos en .dropdown/.d-inline-block)
   no recibían el mismo trato y se veían desalineados. Dos reglas: la primera centra el contenido
   de CUALQUIER hijo directo del row (header-item o wrapper); la segunda alcanza a los .header-item
   sin importar cuántos niveles de wrapper tengan encima. */
.navbar-header > .d-flex:last-child > * {
    display: flex;
    align-items: center;
    /* #9990529: .d-inline-block de Bootstrap trae display:inline-block!important, que
       gana sobre el display:flex de arriba y saca a ese wrapper (el del engrane, entre
       otros) de la negociación de flex del row salvo por align-self (heredado de
       align-items). Fijar align-self explícito aquí blinda la altura contra cualquier
       diferencia de caja entre wrappers hermanos (<a> vs <button>, con/sin .dropdown). */
    align-self: center !important;
}
.navbar-header > .d-flex:last-child .header-item {
    display: flex;
    align-items: center;
}
/* El badge de la campana (.noti-icon .badge{right:4px} del tema) pelea con la utilidad
   BS5 start-100 (left:100%): un elemento absoluto con left Y right a la vez se estira en
   vez de quedar como píldora. Selector más específico que anula el right del tema cuando
   el badge ya trae el posicionamiento BS5 (translate-middle), dejando solo left:100%
   (top ya lo gana top-0!important de BS5; padding/line-height de .badge quedan intactos). */
.noti-icon .badge.translate-middle {
    right: auto;
}
/* alinear verticalmente TODO el clúster derecho (el engrane salía más alto) */
.navbar-header > .d-flex:last-child { align-items: center; }
/* contadores del header como superposición VISIBLE DENTRO de la esquina sup-der del
   icono (v2, #9990529): el translate(25%,-25%) de la v1 empujaba el badge hacia
   afuera/arriba y, al estar el botón pegado al borde superior del header, lo cortaba.
   Sin transform, apoyado 2px adentro del botón, queda sobre el icono y visible entero. */
.hdr-badge-corner {
    top: 2px !important;
    right: 2px !important;
    left: auto !important;
    bottom: auto !important;
    transform: none !important;
}
</style>

<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasRight" aria-labelledby="offcanvasRightLabel" style="width: 350px;">
    <div class="offcanvas-header bg-light border-bottom">
        <h5 id="offcanvasRightLabel" class="mb-0">

            {{-- Verificar permisos para mostrar hipervínculo o texto plano --}}
            @if(auth()->user()->can('documentation_add_documentation') ||
                auth()->user()->can('documentation_edit_documentation') ||
                auth()->user()->can('documentation_delete_documentation'))
                <a href="{{ url('/administracion/documentation/documentation_menu') }}"
                    class="text-decoration-none"
                    {{-- Ajuste para cambio de color al hacer mouseover --}}
                    style="color: inherit; transition: color 0.2s ease;"
                    onmouseover="this.style.color='#0d6efd'"
                    onmouseout="this.style.color='inherit'"
                    title="Ir a Administración de Documentación">
                    <i class="far fa-folder-open me-2"></i>
                    Documentación
                </a>
            @else
                <i class="far fa-folder-open me-2 text-primary"></i>
                Documentación
            @endif

        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
            aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body p-0 bg-white">
        <documentation-tree-menu></documentation-tree-menu>
    </div>
</div>
