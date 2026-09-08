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
<link rel="stylesheet" href="{{ URL::asset('plugins/quasar/extras/bootstrap-icons/bootstrap-icons.css') }}" type="text/css" />
@php
    try {
        $appCssUrl = mix('css/app.css');
    } catch (\Throwable $e) {
        $appCssUrl = URL::asset('css/app.css');
    }
@endphp
<link rel="stylesheet" href="{{ $appCssUrl }}" type="text/css" />

<!-- Favicon -->
<link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
<link rel="shortcut icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

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
   icono (v3, #9990599): en v2 el botón está pegado al borde superior del header y
   top:2px del botón coincidía con el tope del viewport → el badge se cortaba por
   arriba. Se baja el badge (top:15px) para que quede sobre la esquina sup-der del
   icono (el botón mide 70px con el icono centrado ~35px) y completamente visible. */
.badge.hdr-badge-corner {
    top: 15px !important;
    right: 8px !important;
    left: auto !important;
    bottom: auto !important;
    transform: none !important;
}
</style>
