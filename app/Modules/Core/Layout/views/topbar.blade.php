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

            @isset($notifications)
                <div class="dropdown d-inline-block">
                    <button type="button" class="btn header-item noti-icon position-relative"
                        id="page-header-notifications-dropdown" data-bs-toggle="dropdown" aria-haspopup="true"
                        aria-expanded="false">
                        <i data-feather="bell" class="icon-lg"></i>
                        <span
                            class="badge bg-danger rounded-pill">{{ count($notifications) > 0 ? count($notifications) : 0 }}</span>
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
                    <!-- item-->
                    <a class="dropdown-item" href="{{ url('/perfil/' . auth()->user()->id) }}"><i
                            class="mdi mdi-face-profile font-size-16 align-middle me-1"></i> Perfil</a>
                    <a class="dropdown-item" href="{{ route('profile.password.show') }}"><i
                            class="mdi mdi-lock-reset font-size-16 align-middle me-1"></i> Cambiar contraseña</a>
                    <a class="dropdown-item" href="{{ route('logout') }}"
                        data-spa-skip
                        onclick="event.preventDefault(); window.__clearSidebarState && window.__clearSidebarState(); document.getElementById('logout-form').submit();">
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
/* Centrar verticalmente íconos en header-items directos del lado derecho */
.navbar-header > .d-flex:last-child > .header-item {
    display: flex;
    align-items: center;
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
