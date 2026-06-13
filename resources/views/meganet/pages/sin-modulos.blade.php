<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>Sin módulos | MEGANET</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="{{ asset('/assets/images/favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('/assets/css/preloader.min.css') }}" type="text/css" />
    <link href="{{ asset('/assets/css/bootstrap.min.css') }}" id="bootstrap-style" rel="stylesheet" type="text/css" />
    <link href="{{ asset('/assets/css/icons.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('/assets/css/app.min.css') }}" id="app-style" rel="stylesheet" type="text/css" />
</head>
<body>
<div class="my-5 pt-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="text-center mb-5">
                    <h1 class="display-1 fw-semibold text-muted">
                        <i class="mdi mdi-lock-outline" style="font-size:80px;"></i>
                    </h1>
                    <h4 class="text-uppercase">Tu cuenta no tiene módulos asignados</h4>
                    <small class="text-muted">
                        Contacta a un administrador para que asigne los permisos correspondientes a tu perfil.
                    </small>
                    <div class="mt-4">
                        <p class="text-muted">
                            Sesión activa como: <strong>{{ auth()->user()->name ?? auth()->user()->login_user }}</strong>
                        </p>
                    </div>
                    <div class="mt-4 d-flex gap-2 justify-content-center">
                        <a class="btn btn-outline-secondary waves-effect" href="{{ route('logout') }}"
                           data-spa-skip
                           onclick="event.preventDefault(); window.__clearSidebarState && window.__clearSidebarState(); document.getElementById('logout-form').submit();">
                            Cerrar sesión
                        </a>
                    </div>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>
                </div>
            </div>
        </div>
        <div class="row justify-content-center">
            <div class="col-md-8 col-xl-6">
                <img src="{{ asset('/assets/images/error-img.png') }}" alt="" class="img-fluid opacity-50">
            </div>
        </div>
    </div>
</div>
<script src="{{ asset('/assets/libs/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('/assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
</body>
</html>
