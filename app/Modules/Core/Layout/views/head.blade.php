<!-- Preload de CSS críticos -->
<link rel="preload" href="{{ URL::asset('plugins/quasar/css/quasar.prod.css') }}" as="style"
    onload="this.onload=null;this.rel='stylesheet'">
<link rel="preload" href="{{ URL::asset('assets/css/bootstrap.min.css') }}" as="style" id="bootstrap-style"
    onload="this.onload=null;this.rel='stylesheet'">
<link rel="preload" href="{{ URL::asset('assets/css/app.min.css') }}" as="style" id="app-style"
    onload="this.onload=null;this.rel='stylesheet'">

<!-- Preload de fuentes (si las usas) -->
<link rel="preload" href="{{ URL::asset('plugins/quasar/extras/material-icons/material-icons.css') }}" as="style"
    onload="this.onload=null;this.rel='stylesheet'">

<!-- CSS no crítico (cargado normalmente) -->
<link rel="stylesheet" href="{{ URL::asset('assets/css/preloader.min.css') }}" type="text/css" />
<link rel="stylesheet" href="{{ URL::asset('assets/css/icons.min.css') }}" id="icons-style" type="text/css" />
<link rel="stylesheet" href="{{ URL::asset('css/app.css') }}" type="text/css" />

<!-- Favicon -->
<link rel="shortcut icon" href="{{ URL::asset('assets/images/favicon.ico') }}">

<!-- Fallback para navegadores sin soporte a preload -->
<noscript>
    <link href="{{ URL::asset('plugins/quasar/css/quasar.prod.css') }}" rel="stylesheet">
    <link href="{{ URL::asset('assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ URL::asset('assets/css/app.min.css') }}" rel="stylesheet">
</noscript>

<!-- Hojas de estilo dinámicas -->
@isset($packages['css'])
    @foreach ($packages['css'] as $package_css)
        <link href="{{ URL::asset($package_css->url) }}" rel="stylesheet" type="text/css" />
    @endforeach
@endisset

<!-- CDN externos (considera alojarlos localmente) -->
<link rel="preload" href="https://cdn.datatables.net/1.13.2/css/dataTables.bootstrap5.min.css" as="style"
    onload="this.onload=null;this.rel='stylesheet'">
<noscript>
    <link href="https://cdn.datatables.net/1.13.2/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</noscript>

<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<!-- Script para manejar el preload -->
<script>
    /* Función para cargar CSS con preload */
    function loadCSS(e, n, o, t) {
        "use strict";
        var d = window.document.createElement("link");
        var i = n || window.document.getElementsByTagName("script")[0];
        d.rel = "stylesheet";
        d.href = e;
        d.media = "only x";
        t && (d.id = t);
        i.parentNode.insertBefore(d, i);
        setTimeout(function() {
            d.media = o || "all";
        });
    }
</script>
