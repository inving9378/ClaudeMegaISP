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
        </div>

        <div class="d-flex">
            <div class="dropdown d-sm-inline-block">
                <Mode-Visual-Body user="{{ auth()->user()->id }}" configlayout="{{ json_encode($config) }}">
                </Mode-Visual-Body>
            </div>

            <button class="btn header-item" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasRight"
                aria-controls="offcanvasRight">
                <i class="far fa-folder-open"></i>
            </button>

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



            <div class="dropdown d-inline-block">
                @isset($notifications)
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
                        <a class="dropdown-item" href="{{ route('logout') }}"
                            onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
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
